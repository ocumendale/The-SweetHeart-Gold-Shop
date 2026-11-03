<?php
include 'db.php'; // database connection
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
  exit;
}
$message = '';
$messageType = '';


function log_activity($conn, $action, $description) {
    $sql = "INSERT INTO activity_log (action, description) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $action, $description);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['productName'] ?? null;
    $description = $_POST['description'] ?? null;
    $price = $_POST['price'] ?? null;
    $stock = $_POST['stock'] ?? null;
    $category = $_POST['category'] ?? null;
    $material = $_POST['material'] ?? null;
    $hypoallergenic = $_POST['hypoallergenic'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $size = $_POST['size'] ?? null;

    // Default NULL values for category-specific fields
    $ring_size = $necklace_length = $chain_type = $clasp_type = $closure_type = $bracelet_length = null;

    switch ($category) {
        case 'ring':
            $ring_size = $size ?? null;
            break;
        case 'necklace':
            $necklace_length = $_POST['necklaceLength'] ?? null;
            $chain_type = $_POST['chainType'] ?? null;
            $clasp_type = $_POST['claspType'] ?? null;
            break;
        case 'earring':
            $closure_type = $_POST['closureType'] ?? null;
            break;
        case 'bracelet':
            $bracelet_length = $_POST['braceletLength'] ?? null;
            $closure_type = $_POST['closureType'] ?? null;
            $clasp_type = $_POST['claspType'] ?? null;
            break;
    }

    // Image upload
    $image = null;
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["productImage"]["name"], PATHINFO_EXTENSION);
        $image = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $image;
        
        if ($_FILES["productImage"]["size"] > 5000000) {
            $message = "File is too large. Maximum size is 5MB.";
            $messageType = "danger";
        } else {
            $imageFileType = strtolower($file_extension);
            if(!in_array($imageFileType, ["jpg", "jpeg", "png"])) {
                $message = "Only JPG, JPEG, PNG files are allowed.";
                $messageType = "danger";
            } else {
                if (!move_uploaded_file($_FILES["productImage"]["tmp_name"], $target_file)) {
                    $message = "Error uploading file. Please check directory permissions.";
                    $messageType = "danger";
                }
            }
        }
    } else if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] !== 4) {
        $message = "Error uploading file.";
        $messageType = "danger";
    }

    // Only proceed if no errors
    if (empty($message)) {
        $stmt = $conn->prepare("
            INSERT INTO products 
            (product_name, description, price, stock, image, category, material, hypoallergenic, gender, 
             ring_size, necklace_length, chain_type, clasp_type, closure_type, bracelet_length)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "ssdisssssssssss",
                $product_name, $description, $price, $stock, $image, $category,
                $material, $hypoallergenic, $gender, $ring_size, $necklace_length,
                $chain_type, $clasp_type, $closure_type, $bracelet_length
            );

            if ($stmt->execute()) {
                // ✅ Log activity only after success
                log_activity($conn, "Add Product", "Added product: $product_name");

                $stmt->close();
                $conn->close();

                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $message = "Database Error: " . $stmt->error;
                $messageType = "danger";
                $stmt->close();
            }
        } else {
            $message = "Database Prepare Error: " . $conn->error;
            $messageType = "danger";
        }
    }

    if(isset($conn)) $conn->close();
}

// Success message after redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Product added successfully!";
    $messageType = "success";
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Product - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="CSS/Addproduct.css">
  </head>
  <body>
    <!-- Sidebar -->
    <div class="d-flex flex-column flex-shrink-0 p-3 sidebar">
      <br><br><br>
      <div class="logo">
        <div class="sweetheart">SweetHeart</div>
        <div class="goldshop">Gold Shop</div>
      </div>
      <br><br><br>
      <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item mb-4">
          <a href="dashboard.php" class="nav-link text-white">
            <img src="../icons/home.png" alt="Dashboard" class="nav-icon" /> Dashboard
          </a>
        </li>
        <li class="mb-4">
          <a href="inventory.php" class="nav-link active">
            <img src="../icons/inventory.png" alt="Inventory" class="nav-icon" /> Inventory
          </a>
        </li>
        <li class="mb-4">
          <a href="orders.php" class="nav-link text-white">
            <img src="../icons/orders.png" alt="Orders" class="nav-icon" /> Orders
          </a>
        </li>
        <li class="mb-4">
          <a href="customers.php" class="nav-link text-white">
            <img src="../icons/customers.png" alt="Customers" class="nav-icon" /> Customers
          </a>
        </li>
        <li class="mb-4">
          <a href="sales_report.php" class="nav-link text-white">
            <img src="../icons/salesreport.png" alt="salesreport" class="nav-icon" /> Sales Report
          </a>
        </li>
        <li class="mb-4">
          <a href="activitylog.php" class="nav-link text-white">
            <img src="../icons/restore.png" alt="Activity Log and Recycle Bin" class="nav-icon" />Log and Bin
          </a>
        </li>
      </ul>
    </div>

    <!-- Content Area -->
    <div class="content-area">
      <!-- Header - Match Sales Report exactly -->
      <div class="header">
        <div>
          <h1>ADD PRODUCT</h1>
        </div>    
        <div>
          <a href="logout.php" class="logout-btn">Logout</a>
        </div>  
      </div>

      <!-- Success/Error Message Display -->
      <?php if (!empty($message)): ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <!-- View Products Button -->
      <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
        <a href="inventory.php" style="text-decoration: none;">
          <button type="button" class="view-products-btn">
            <img src="../icons/inventory.png" alt="View" style="width:20px; height:20px;">
            View Products
          </button>
        </a>
      </div>

      <!-- Product Form Card -->
      <div class="card">
        <h3>Product Information</h3>
        <form id="productForm" method="POST" enctype="multipart/form-data">
          <div class="row g-4">
            <!-- LEFT SIDE -->
            <div class="col-md-6">
              
              <!-- Product Name full width -->
              <div class="mb-3">
                <label for="productName" class="form-label">Product Name</label>
                <input type="text" id="productName" name="productName" class="form-control" placeholder="Enter Product Name" required>
              </div>
          
              <!-- Price + Category -->
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="price" class="form-label">Price</label>
                    <input type="number" id="price" name="price" class="form-control" placeholder="0.00" step="0.01" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-select" required>
                      <option value="">Select Category</option>
                      <option value="necklace">Necklace</option>
                      <option value="bracelet">Bracelet</option>
                      <option value="ring">Ring</option>
                      <option value="earring">Earring</option>
                    </select>
                  </div>
                </div>
              </div>
          
              <!-- Material + Stock -->
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="material" class="form-label">Material</label>
                    <select id="material" name="material" class="form-select">
                      <option value="">Select Material</option>
                      <option value="18K Saudi Gold">18K Saudi Gold</option>
                      <option value="18K Yellow Gold">18K Yellow Gold</option>
                      <option value="18K White Gold">18K White Gold</option>
                      <option value="18K Rose Gold">18K Rose Gold</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="stock" class="form-label">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" class="form-control" placeholder="0" min="0" required>
                  </div>
                </div>
              </div>
          
              <!-- Size + Gender -->
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="size" class="form-label">Size</label>
                    <select id="size" name="size" class="form-select">
                      <option value="">Select Size</option>
                      <option value="small">Small</option>
                      <option value="medium">Medium</option>
                      <option value="large">Large</option>
                      <!-- Ring sizes will be added dynamically -->
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select id="gender" name="gender" class="form-select">
                      <option value="">Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Unisex">Unisex</option>
                    </select>
                  </div>
                </div>
              </div>
          
              <!-- Chain Type + Necklace Length (Necklace only) -->
              <div class="row category-field" data-category="necklace">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="chainType" class="form-label">Chain Type</label>
                    <select id="chainType" name="chainType" class="form-select">
                      <option value="">Select Chain Type</option>
                      <option value="Cable">Cable</option>
                      <option value="Box">Box</option>
                      <option value="Rope">Rope</option>
                      <option value="Snake">Snake</option>
                      <option value="Bead">Bead</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="necklaceLength" class="form-label">Necklace Length</label>
                    <select id="necklaceLength" name="necklaceLength" class="form-select">
                      <option value="">Select Length</option>
                      <option value="16">16" (40 cm)</option>
                      <option value="18">18" (45 cm)</option>
                      <option value="20">20" (50 cm)</option>
                      <option value="24">24" (60 cm)</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Closure Type (Different for Earrings vs Necklace/Bracelet) -->
              <div class="row category-field" data-category="earring">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="closureType" class="form-label">Closure Type</label>
                    <select id="closureType" name="closureType" class="form-select">
                      <option value="">Select Closure Type</option>
                      <option value="Push Back">Push Back</option>
                      <option value="Screw Back">Screw Back</option>
                      <option value="Lever Back">Lever Back</option>
                      <option value="Hook">Hook</option>
                      <option value="Clip On">Clip On</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Clasp Type + Closure Type (Necklace & Bracelet) -->
              <div class="row category-field" data-category="necklace bracelet">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="claspType" class="form-label">Clasp Type</label>
                    <select id="claspType" name="claspType" class="form-select">
                      <option value="">Select Clasp Type</option>
                      <option value="Lobster Claw">Lobster Claw</option>
                      <option value="Spring Ring">Spring Ring</option>
                      <option value="Toggle">Toggle</option>
                      <option value="Magnetic">Magnetic</option>
                      <option value="Fishhook">Fishhook</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="closureTypeBracelet" class="form-label">Closure Type</label>
                    <select id="closureTypeBracelet" name="closureType" class="form-select">
                      <option value="">Select Closure Type</option>
                      <option value="Magnetic">Magnetic</option>
                      <option value="Hook and Eye">Hook and Eye</option>
                      <option value="Toggle">Toggle</option>
                      <option value="Sliding">Sliding</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Hypoallergenic (All categories) -->
              <div class="row category-field" data-category="necklace bracelet ring earring">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="hypoallergenic" class="form-label">Hypoallergenic</label>
                    <select id="hypoallergenic" name="hypoallergenic" class="form-select">
                      <option value="">Select</option>
                      <option value="yes">Yes</option>
                      <option value="no">No</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Bracelet Length (Bracelet only) -->
              <div class="row category-field" data-category="bracelet">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="braceletLength" class="form-label">Bracelet Length</label>
                    <select id="braceletLength" name="braceletLength" class="form-select">
                      <option value="">Select Length</option>
                      <option value="6">6 inches</option>
                      <option value="6.5">6.5 inches</option>
                      <option value="7">7 inches</option>
                      <option value="7.5">7.5 inches</option>
                      <option value="8">8 inches</option>
                      <option value="8.5">8.5 inches</option>
                      <option value="9">9 inches</option>
                    </select>
                  </div>
                </div>
              </div>

            </div>
          
            <!-- RIGHT SIDE -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="7" 
                          placeholder="Enter product description..." style="height: 250px;" required></textarea>
              </div>
          
              <div class="mb-3">
                <label for="productImage" class="form-label">Product Image</label>
                <div class="upload-box" id="uploadBox">
                  <input type="file" id="productImage" name="productImage" accept="image/png, image/jpeg, image/jpg" hidden>
                  <div id="uploadContent">
                    <div class="text-center" style="cursor:pointer;">
                      <img src="../icons/cam.png" width="100" alt="Upload">
                      <p class="mt-2 mb-1">Click to upload product image</p>
                      <small>Supported formats: JPG, PNG (max. 5MB)</small>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Buttons -->
              <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px;">
                <button type="button" class="btn-cancel" onclick="window.location.href='inventory.php'">Cancel</button>
                <button type="submit" class="btn-add">Add Product</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
      // Required field asterisks
      document.addEventListener("DOMContentLoaded", function () {
        const fields = document.querySelectorAll("form input, form select, form textarea");
        
        fields.forEach(field => {
          const label = field.closest(".mb-3")?.querySelector("label");
          
          if (label) {
            updateStar(field, label);
            field.addEventListener("input", () => updateStar(field, label));
            field.addEventListener("change", () => updateStar(field, label));
          }
        });
        
        function updateStar(field, label) {
          let star = label.querySelector(".required-star");
          
          if (!field.value.trim()) {
            if (!star && field.hasAttribute('required')) {
              star = document.createElement("span");
              star.textContent = " *";
              star.classList.add("text-danger", "required-star");
              label.appendChild(star);
            }
          } else {
            if (star) star.remove();
          }
        }
      });

      // Category-specific fields visibility
      document.addEventListener("DOMContentLoaded", function () {
        const categorySelect = document.getElementById("category");
        const categoryFields = document.querySelectorAll(".category-field");
        const sizeSelect = document.getElementById("size");
        
        function updateCategoryFields() {
          const selected = categorySelect.value;
          
          // Update size options based on category
          if (selected === 'ring') {
            sizeSelect.innerHTML = `
              <option value="">Select Ring Size</option>
              <option value="4">Size 4</option>
              <option value="5">Size 5</option>
              <option value="6">Size 6</option>
              <option value="7">Size 7</option>
              <option value="8">Size 8</option>
              <option value="9">Size 9</option>
              <option value="10">Size 10</option>
              <option value="11">Size 11</option>
              <option value="12">Size 12</option>
              <option value="13">Size 13</option>
            `;
          } else {
            sizeSelect.innerHTML = `
              <option value="">Select Size</option>
              <option value="small">Small</option>
              <option value="medium">Medium</option>
              <option value="large">Large</option>
            `;
          }
          
          // Show/hide category-specific fields
          categoryFields.forEach(field => {
            const categories = field.getAttribute("data-category").split(" ");
            if (categories.includes(selected)) {
              field.style.display = "flex";
              // Enable required fields for visible categories
              const inputs = field.querySelectorAll('select, input');
              inputs.forEach(input => {
                if (selected === 'bracelet' && input.name === 'braceletLength') {
                  input.setAttribute('required', 'required');
                } else if (selected === 'earring' && input.name === 'closureType') {
                  input.setAttribute('required', 'required');
                }
              });
            } else {
              field.style.display = "none";
              // Remove required attribute from hidden fields
              const inputs = field.querySelectorAll('select, input');
              inputs.forEach(input => {
                input.removeAttribute('required');
                input.value = ''; // Clear values
              });
            }
          });
        }
        
        updateCategoryFields();
        categorySelect.addEventListener("change", updateCategoryFields);
      });

      // Enhanced image preview with better error handling
      document.getElementById('productImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const uploadContent = document.getElementById('uploadContent');
        
        if (file) {
          // Check file type
          if (!file.type.match(/^image\/(jpeg|jpg|png)$/)) {
            alert('Please select a valid image file (JPG, JPEG, or PNG).');
            this.value = '';
            resetUploadBox();
            return;
          }
          
          // Check file size (5MB)
          if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            this.value = '';
            resetUploadBox();
            return;
          }
          
          const reader = new FileReader();
          reader.onload = function(e) {
            uploadContent.innerHTML = `
              <img src="${e.target.result}" class="preview-image" alt="Preview">
              <p class="mt-2">Click to change image</p>
            `;
          };
          reader.onerror = function() {
            console.error('Error reading file');
            alert('Error reading file. Please try again.');
            resetUploadBox();
          };
          reader.readAsDataURL(file);
        } else {
          resetUploadBox();
        }
      });

      // Function to reset upload box to original state
      function resetUploadBox() {
        document.getElementById('uploadContent').innerHTML = `
          <div class="text-center" style="cursor:pointer;">
            <img src="../icons/cam.png" width="100" alt="Upload">
            <p class="mt-2 mb-1">Click to upload product image</p>
            <small>Supported formats: JPG, PNG (max. 5MB)</small>
          </div>
        `;
      }

      // Make upload box clickable
      document.getElementById('uploadBox').addEventListener('click', function(e) {
        if (e.target.id !== 'productImage') {
          document.getElementById('productImage').click();
        }
      });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  </body>
</html>
<?php
// Database connection - same as inventory.php
include 'db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../customer/login.php");
    exit;
}

$message = '';
$messageType = '';
$product = null;

// ========== STEP 1: LOAD EXISTING PRODUCT DATA ==========
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $productId = intval($_GET['id']);
    
    $query = "SELECT * FROM products WHERE id = ? AND is_deleted = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        header("Location: inventory.php?error=product_not_found");
        exit();
    }
} else {
    header("Location: inventory.php");
    exit();
}

// ========== STEP 2: HANDLE FORM SUBMISSION (UPDATE) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    
    $productId = intval($_POST['product_id']);
    
    // Get all form values
    $productName = mysqli_real_escape_string($conn, $_POST['productName']);
    $price = floatval($_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $material = mysqli_real_escape_string($conn, $_POST['material'] ?? '');
    $stock = intval($_POST['stock']);
    $size = mysqli_real_escape_string($conn, $_POST['size'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Category-specific fields
    $chainType = mysqli_real_escape_string($conn, $_POST['chainType'] ?? '');
    $necklaceLength = mysqli_real_escape_string($conn, $_POST['necklaceLength'] ?? '');
    $claspType = mysqli_real_escape_string($conn, $_POST['claspType'] ?? '');
    $closureType = mysqli_real_escape_string($conn, $_POST['closureType'] ?? '');
    $hypoallergenic = mysqli_real_escape_string($conn, $_POST['hypoallergenic'] ?? '');
    $braceletLength = mysqli_real_escape_string($conn, $_POST['braceletLength'] ?? '');
    
    // Keep existing image by default
    $imagePath = $product['image'];
    
    // Handle new image upload (optional)
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES['productImage']['type'], $allowedTypes) && 
            $_FILES['productImage']['size'] <= $maxSize) {
            
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . $productId . '_' . time() . '.' . $extension;
            $uploadPath = $filename; // Store only filename, not full path
            
            if (move_uploaded_file($_FILES['productImage']['tmp_name'], $uploadDir . $filename)) {
                // Delete old image if exists
                if (!empty($product['image']) && file_exists('uploads/' . $product['image'])) {
                    unlink('uploads/' . $product['image']);
                }
                $imagePath = $uploadPath;
            }
        }
    }
    
    // ========== UPDATE QUERY (NOT INSERT!) ==========
    $updateQuery = "UPDATE products SET 
        product_name = ?,
        price = ?,
        category = ?,
        material = ?,
        stock = ?,
        ring_size = ?,
        gender = ?,
        description = ?,
        image = ?,
        chain_type = ?,
        necklace_length = ?,
        clasp_type = ?,
        closure_type = ?,
        hypoallergenic = ?,
        bracelet_length = ?
        WHERE id = ?";
    
    $stmt = $conn->prepare($updateQuery);
$stmt->bind_param(
    "sdssissssssssssi",
    $productName, 
    $price, 
    $category, 
    $material, 
    $stock, 
    $size, 
    $gender, 
    $description, 
    $imagePath, 
    $chainType, 
    $necklaceLength, 
    $claspType, 
    $closureType, 
    $hypoallergenic, 
    $braceletLength, 
    $productId
);

    
    if ($stmt->execute()) {
        $message = "Product updated successfully!";
        $messageType = "success";
        
        // Reload updated product data
        $query = "SELECT * FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "Error updating product: " . $conn->error;
        $messageType = "danger";
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Product - SweetHeart Gold Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Qwigley&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="CSS/Editproduct.css">
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
            <img src="../icons/salesreport.png" alt="Sales Report" class="nav-icon" /> Sales Report
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
    <div class="content">
      <!-- Header -->
      <div class="header-container">
        <h1 class="page-title">EDIT PRODUCT</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>

      <!-- Success/Error Message Display -->
      <?php if (!empty($message)): ?>
      <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php endif; ?>

      <!-- View Products Button -->
      <div class="d-flex justify-content-end mb-3">
        <a href="inventory.php" class="view-products-btn">
          <img src="../icons/inventory.png" alt="View" style="width:20px; height:20px;">
          View Products
        </a>
      </div>

      <!-- Product Form -->
      <div class="form-container">
        <form id="productForm" method="POST" enctype="multipart/form-data">
          <!-- CRITICAL: Hidden product_id field for UPDATE -->
          <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
          
          <div class="row g-4">
            <!-- LEFT SIDE -->
            <div class="col-md-6">
              
              <!-- Product Name -->
              <div class="mb-3">
                <label for="productName" class="form-label">Product Name</label>
                <input type="text" id="productName" name="productName" class="form-control" 
                       value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                       placeholder="Enter Product Name" required>
              </div>
          
              <!-- Price + Category -->
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="price" class="form-label">Price</label>
                  <input type="number" id="price" name="price" class="form-control" 
                         value="<?php echo $product['price']; ?>" 
                         placeholder="0.00" step="0.01" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="category" class="form-label">Category</label>
                  <select id="category" name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <option value="necklace" <?php echo ($product['category'] == 'necklace') ? 'selected' : ''; ?>>Necklace</option>
                    <option value="bracelet" <?php echo ($product['category'] == 'bracelet') ? 'selected' : ''; ?>>Bracelet</option>
                    <option value="ring" <?php echo ($product['category'] == 'ring') ? 'selected' : ''; ?>>Ring</option>
                    <option value="earring" <?php echo ($product['category'] == 'earring') ? 'selected' : ''; ?>>Earring</option>
                  </select>
                </div>
              </div>
          
              <!-- Material + Stock -->
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="material" class="form-label">Material</label>
                  <select id="material" name="material" class="form-select">
                    <option value="">Select Material</option>
                    <option value="18K Saudi Gold" <?php echo ($product['material'] == '18K Saudi Gold') ? 'selected' : ''; ?>>18K Saudi Gold</option>
                    <option value="18K Yellow Gold" <?php echo ($product['material'] == '18K Yellow Gold') ? 'selected' : ''; ?>>18K Yellow Gold</option>
                    <option value="18K White Gold" <?php echo ($product['material'] == '18K White Gold') ? 'selected' : ''; ?>>18K White Gold</option>
                    <option value="18K Rose Gold" <?php echo ($product['material'] == '18K Rose Gold') ? 'selected' : ''; ?>>18K Rose Gold</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="stock" class="form-label">Stock Quantity</label>
                  <input type="number" id="stock" name="stock" class="form-control" 
                         value="<?php echo $product['stock']; ?>" 
                         placeholder="0" min="0" required>
                </div>
              </div>
          
              <!-- Size + Gender -->
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="size" class="form-label">Size</label>
                  <select id="size" name="size" class="form-select">
                    <option value="">Select Size</option>
                    <option value="small" <?php echo ($product['ring_size'] == 'small') ? 'selected' : ''; ?>>Small</option>
                    <option value="medium" <?php echo ($product['ring_size'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="large" <?php echo ($product['ring_size'] == 'large') ? 'selected' : ''; ?>>Large</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="gender" class="form-label">Gender</label>
                  <select id="gender" name="gender" class="form-select">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($product['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($product['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Unisex" <?php echo ($product['gender'] == 'Unisex') ? 'selected' : ''; ?>>Unisex</option>
                  </select>
                </div>
              </div>
          
              <!-- Chain Type + Necklace Length (Necklace only) -->
              <div class="row category-field" data-category="necklace">
                <div class="col-md-6 mb-3">
                  <label for="chainType" class="form-label">Chain Type</label>
                  <select id="chainType" name="chainType" class="form-select">
                    <option value="">Select Chain Type</option>
                    <option value="Cable" <?php echo ($product['chain_type'] == 'Cable') ? 'selected' : ''; ?>>Cable</option>
                    <option value="Box" <?php echo ($product['chain_type'] == 'Box') ? 'selected' : ''; ?>>Box</option>
                    <option value="Rope" <?php echo ($product['chain_type'] == 'Rope') ? 'selected' : ''; ?>>Rope</option>
                    <option value="Snake" <?php echo ($product['chain_type'] == 'Snake') ? 'selected' : ''; ?>>Snake</option>
                    <option value="Bead" <?php echo ($product['chain_type'] == 'Bead') ? 'selected' : ''; ?>>Bead</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="necklaceLength" class="form-label">Necklace Length</label>
                  <select id="necklaceLength" name="necklaceLength" class="form-select">
                    <option value="">Select Length</option>
                    <option value="16" <?php echo ($product['necklace_length'] == '16') ? 'selected' : ''; ?>>16" (40 cm)</option>
                    <option value="18" <?php echo ($product['necklace_length'] == '18') ? 'selected' : ''; ?>>18" (45 cm)</option>
                    <option value="20" <?php echo ($product['necklace_length'] == '20') ? 'selected' : ''; ?>>20" (50 cm)</option>
                    <option value="24" <?php echo ($product['necklace_length'] == '24') ? 'selected' : ''; ?>>24" (60 cm)</option>
                  </select>
                </div>
              </div>

              <!-- Closure Type (Earrings) -->
              <div class="row category-field" data-category="earring">
                <div class="col-md-6 mb-3">
                  <label for="closureType" class="form-label">Closure Type</label>
                  <select id="closureType" name="closureType" class="form-select">
                    <option value="">Select Closure Type</option>
                    <option value="Push Back" <?php echo ($product['closure_type'] == 'Push Back') ? 'selected' : ''; ?>>Push Back</option>
                    <option value="Screw Back" <?php echo ($product['closure_type'] == 'Screw Back') ? 'selected' : ''; ?>>Screw Back</option>
                    <option value="Lever Back" <?php echo ($product['closure_type'] == 'Lever Back') ? 'selected' : ''; ?>>Lever Back</option>
                    <option value="Hook" <?php echo ($product['closure_type'] == 'Hook') ? 'selected' : ''; ?>>Hook</option>
                    <option value="Clip On" <?php echo ($product['closure_type'] == 'Clip On') ? 'selected' : ''; ?>>Clip On</option>
                  </select>
                </div>
              </div>

              <!-- Clasp Type + Closure Type (Necklace & Bracelet) -->
              <div class="row category-field" data-category="necklace bracelet">
                <div class="col-md-6 mb-3">
                  <label for="claspType" class="form-label">Clasp Type</label>
                  <select id="claspType" name="claspType" class="form-select">
                    <option value="">Select Clasp Type</option>
                    <option value="Lobster Claw" <?php echo ($product['clasp_type'] == 'Lobster Claw') ? 'selected' : ''; ?>>Lobster Claw</option>
                    <option value="Spring Ring" <?php echo ($product['clasp_type'] == 'Spring Ring') ? 'selected' : ''; ?>>Spring Ring</option>
                    <option value="Toggle" <?php echo ($product['clasp_type'] == 'Toggle') ? 'selected' : ''; ?>>Toggle</option>
                    <option value="Magnetic" <?php echo ($product['clasp_type'] == 'Magnetic') ? 'selected' : ''; ?>>Magnetic</option>
                    <option value="Fishhook" <?php echo ($product['clasp_type'] == 'Fishhook') ? 'selected' : ''; ?>>Fishhook</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="closureTypeBracelet" class="form-label">Closure Type</label>
                  <select id="closureTypeBracelet" name="closureType" class="form-select">
                    <option value="">Select Closure Type</option>
                    <option value="Magnetic" <?php echo ($product['closure_type'] == 'Magnetic') ? 'selected' : ''; ?>>Magnetic</option>
                    <option value="Hook and Eye" <?php echo ($product['closure_type'] == 'Hook and Eye') ? 'selected' : ''; ?>>Hook and Eye</option>
                    <option value="Toggle" <?php echo ($product['closure_type'] == 'Toggle') ? 'selected' : ''; ?>>Toggle</option>
                    <option value="Sliding" <?php echo ($product['closure_type'] == 'Sliding') ? 'selected' : ''; ?>>Sliding</option>
                  </select>
                </div>
              </div>

              <!-- Hypoallergenic (All categories) -->
              <div class="row category-field" data-category="necklace bracelet ring earring">
                <div class="col-md-6 mb-3">
                  <label for="hypoallergenic" class="form-label">Hypoallergenic</label>
                  <select id="hypoallergenic" name="hypoallergenic" class="form-select">
                    <option value="">Select</option>
                    <option value="yes" <?php echo ($product['hypoallergenic'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                    <option value="no" <?php echo ($product['hypoallergenic'] == 'no') ? 'selected' : ''; ?>>No</option>
                  </select>
                </div>
              </div>

              <!-- Bracelet Length (Bracelet only) -->
              <div class="row category-field" data-category="bracelet">
                <div class="col-md-6 mb-3">
                  <label for="braceletLength" class="form-label">Bracelet Length</label>
                  <select id="braceletLength" name="braceletLength" class="form-select">
                    <option value="">Select Length</option>
                    <option value="6" <?php echo ($product['bracelet_length'] == '6') ? 'selected' : ''; ?>>6 inches</option>
                    <option value="6.5" <?php echo ($product['bracelet_length'] == '6.5') ? 'selected' : ''; ?>>6.5 inches</option>
                    <option value="7" <?php echo ($product['bracelet_length'] == '7') ? 'selected' : ''; ?>>7 inches</option>
                    <option value="7.5" <?php echo ($product['bracelet_length'] == '7.5') ? 'selected' : ''; ?>>7.5 inches</option>
                    <option value="8" <?php echo ($product['bracelet_length'] == '8') ? 'selected' : ''; ?>>8 inches</option>
                    <option value="8.5" <?php echo ($product['bracelet_length'] == '8.5') ? 'selected' : ''; ?>>8.5 inches</option>
                    <option value="9" <?php echo ($product['bracelet_length'] == '9') ? 'selected' : ''; ?>>9 inches</option>
                  </select>
                </div>
              </div>

            </div>
          
            <!-- RIGHT SIDE -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="7" 
                          placeholder="Enter product description..." style="height: 250px;" required><?php echo htmlspecialchars($product['description']); ?></textarea>
              </div>
          
              <div class="mb-3">
                <label for="productImage" class="form-label">Product Image</label>
                <div class="upload-box" id="uploadBox">
                  <input type="file" id="productImage" name="productImage" accept="image/png, image/jpeg, image/jpg" hidden>
                  <div id="uploadContent">
                    <?php if (!empty($product['image']) && file_exists('uploads/' . $product['image'])): ?>
                      <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" class="preview-image" alt="Product">
                      <p class="mt-2" style="font-size: 13px; color: #666;">Click to change image</p>
                    <?php else: ?>
                      <div class="text-center" style="cursor:pointer;">
                        <img src="../icons/cam.png" width="80" alt="Upload">
                        <p class="mt-2 mb-1" style="font-weight: 500; color: #5a4a3a;">Click to upload product image</p>
                        <small style="color: #999;">Supported formats: JPG, PNG (max. 5MB)</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              
              <!-- Buttons -->
              <div class="d-flex justify-content-end gap-3 mt-4">
                <button type="button" class="btn-cancel" onclick="window.location.href='inventory.php'">Cancel</button>
                <button type="submit" class="btn-add">Update Product</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
      // Store current category for initialization
      const currentCategory = "<?php echo $product['category']; ?>";
      
      // Category-specific fields visibility
      document.addEventListener("DOMContentLoaded", function () {
        const categorySelect = document.getElementById("category");
        const categoryFields = document.querySelectorAll(".category-field");
        const sizeSelect = document.getElementById("size");
        
        function updateCategoryFields() {
          const selected = categorySelect.value;
          
          // Update size options based on category
          if (selected === 'ring') {
            const currentSize = "<?php echo $product['ring_size']; ?>";
            sizeSelect.innerHTML = `
              <option value="">Select Ring Size</option>
              <option value="4" ${currentSize === '4' ? 'selected' : ''}>Size 4</option>
              <option value="5" ${currentSize === '5' ? 'selected' : ''}>Size 5</option>
              <option value="6" ${currentSize === '6' ? 'selected' : ''}>Size 6</option>
              <option value="7" ${currentSize === '7' ? 'selected' : ''}>Size 7</option>
              <option value="8" ${currentSize === '8' ? 'selected' : ''}>Size 8</option>
              <option value="9" ${currentSize === '9' ? 'selected' : ''}>Size 9</option>
              <option value="10" ${currentSize === '10' ? 'selected' : ''}>Size 10</option>
              <option value="11" ${currentSize === '11' ? 'selected' : ''}>Size 11</option>
              <option value="12" ${currentSize === '12' ? 'selected' : ''}>Size 12</option>
              <option value="13" ${currentSize === '13' ? 'selected' : ''}>Size 13</option>
            `;
          }
          
          // Show/hide category-specific fields
          categoryFields.forEach(field => {
            const categories = field.getAttribute("data-category").split(" ");
            if (categories.includes(selected)) {
              field.style.display = "flex";
            } else {
              field.style.display = "none";
            }
          });
        }
        
        // Initialize on page load
        updateCategoryFields();
        categorySelect.addEventListener("change", updateCategoryFields);
      });

      // Image preview
      document.getElementById('productImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const uploadContent = document.getElementById('uploadContent');
        
        if (file) {
          if (!file.type.match(/^image\/(jpeg|jpg|png)$/)) {
            alert('Please select a valid image file (JPG, JPEG, or PNG).');
            this.value = '';
            return;
          }
          
          if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            this.value = '';
            return;
          }
          
          const reader = new FileReader();
          reader.onload = function(e) {
            uploadContent.innerHTML = `
              <img src="${e.target.result}" class="preview-image" alt="Preview">
              <p class="mt-2" style="font-size: 13px; color: #666;">Click to change image</p>
            `;
          };
          reader.readAsDataURL(file);
        }
      });

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
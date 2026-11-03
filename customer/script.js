fetch('getProducts.php')
  .then(res => res.json())
  .then(data => {
    const container = document.getElementById('product-container');
    data.forEach(product => {
      const card = document.createElement('div');
      card.className = 'product';
      card.innerHTML = `
        <img src="images/${product.image}" alt="${product.name}">
        <h3>${product.name}</h3>
        <p>₱${product.price}</p>
        <p>${product.description}</p>
      `;
      container.appendChild(card);
    });
  })
  .catch(error => {
    console.error("Error fetching products:", error);
  });

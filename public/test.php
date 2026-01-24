<!DOCTYPE html>
<html>
<head>
    <title>Test Addons API</title>
</head>
<body>
    <h1>Test Addons API</h1>
    <label>Product ID: <input type="number" id="productId" value="1"></label>
    <button onclick="testAPI()">Test API</button>
    
    <div id="result" style="margin-top: 20px; padding: 10px; background: #f5f5f5;"></div>
    
    <script>
    function testAPI() {
        const productId = document.getElementById('productId').value;
        const resultDiv = document.getElementById('result');
        
        resultDiv.innerHTML = '<p>Loading...</p>';
        
        fetch(`api/get-product-addons.php?product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                let html = `<h3>API Response for Product ID: ${productId}</h3>`;
                
                if (data.success) {
                    html += `<p><strong>Product:</strong> ${data.product.name} (₱${data.product.price})</p>`;
                    html += `<p><strong>Total Addons:</strong> ${data.addons.length}</p>`;
                    
                    if (data.addons.length > 0) {
                        html += `<h4>Addons List:</h4><ul>`;
                        data.addons.forEach(addon => {
                            const isGlobal = addon.is_global == 1 ? ' <span style="color:blue">(GLOBAL)</span>' : '';
                            html += `<li>
                                <strong>${addon.name}</strong>${isGlobal} - 
                                ₱${addon.price} - 
                                ${addon.description || 'No description'}
                            </li>`;
                        });
                        html += `</ul>`;
                    } else {
                        html += `<p style="color:red">No addons found!</p>`;
                    }
                } else {
                    html += `<p style="color:red">Error: ${data.message}</p>`;
                }
                
                resultDiv.innerHTML = html;
            })
            .catch(error => {
                resultDiv.innerHTML = `<p style="color:red">Error: ${error.message}</p>`;
            });
    }
    
    // Test on load
    window.onload = testAPI;
    </script>
</body>
</html>
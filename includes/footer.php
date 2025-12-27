    </main>
    
    <footer style="background-color: #333; color: white; padding: 3rem 0; margin-top: 3rem;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div>
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">CleanTech</h2>
                    <p>Layanan cleaning service profesional dengan teknologi terkini untuk rumah dan kantor Anda.</p>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 1rem;">Layanan</h3>
                    <ul style="list-style: none;">
                        <li><a href="#" style="color: #ccc; text-decoration: none;">Home Cleaning</a></li>
                        <li><a href="#" style="color: #ccc; text-decoration: none;">Office Cleaning</a></li>
                        <li><a href="#" style="color: #ccc; text-decoration: none;">Deep Cleaning</a></li>
                        <li><a href="#" style="color: #ccc; text-decoration: none;">Regular Cleaning</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 1rem;">Kontak</h3>
                    <p>Email: info@cleantech.id</p>
                    <p>Telepon: (021) 1234-5678</p>
                    <p>Alamat: Jl. Kebersihan No.22, Tangerang</p>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 1rem;">Follow Us</h3>
                    <div style="display: flex; gap: 1rem;">
                        <a href="#" style="color: #ccc; text-decoration: none;">Facebook</a>
                        <a href="#" style="color: #ccc; text-decoration: none;">Instagram</a>
                        <a href="#" style="color: #ccc; text-decoration: none;">Twitter</a>
                    </div>
                </div>
            </div>
            
            <div style="border-top: 1px solid #444; margin-top: 2rem; padding-top: 1rem; text-align: center;">
                <p>&copy; <?php echo date('Y'); ?> CleanTech. Indika Saputra - 221011402182.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Close alert messages
        document.querySelectorAll('.close-alert').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>
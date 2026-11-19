</div> <!-- /.container -->

<footer class="footer-main bg-light py-3 mt-auto text-center">
  <div class="container d-flex flex-column align-items-center justify-content-center">
    <div class="mb-2">
      <a href="#" class="footer-icon mx-2" title="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="#" class="footer-icon mx-2" title="Twitter"><i class="fab fa-twitter"></i></a>
      <a href="#" class="footer-icon mx-2" title="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" class="footer-icon mx-2" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
    </div>
    <small class="footer-copy">&copy; <?= date('Y') ?> DocCenter. All rights reserved.</small>
  </div>
</footer>

<style>
.footer-main {
  background: linear-gradient(90deg, #e0e7ff 0%, #f8fafc 100%);
  border-top-left-radius: 1.5rem;
  border-top-right-radius: 1.5rem;
  box-shadow: 0 -2px 16px rgba(37,99,235,0.07);
  font-family: 'Poppins', 'Inter', Arial, sans-serif;
  position: relative;
  z-index: 10;
}
.footer-icon {
  color: #2563eb;
  font-size: 1.3rem;
  transition: color 0.2s, transform 0.2s;
  border-radius: 50%;
  padding: 0.4rem;
  background: #f1f5f9;
  margin: 0 0.2rem;
  display: inline-block;
}
.footer-icon:hover {
  color: #fff;
  background: #2563eb;
  transform: translateY(-2px) scale(1.1);
}
.footer-copy {
  color: #64748b;
  font-size: 1rem;
  margin-top: 0.2rem;
}
</style>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

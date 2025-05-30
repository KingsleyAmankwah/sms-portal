    <footer class="footer footer-black footer-white">
      <div class="container-fluid">
        <div class="row">
          <div class="credits ml-auto">
            <span class="copyright">
              ©
              <script>
                document.write(new Date().getFullYear());
              </script>
              , made with <i class="fa fa-heart heart"></i> by TekSed Inc Team
            </span>
          </div>
        </div>
      </div>
    </footer>
    </div>
    </div>


    <!--   Core JS Files   -->
    <script src="../assets/js/core/jquery.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.jquery.min.js"></script>
    <script src="../assets/sweetalert/sweetalert2.all.min.js"></script>

    <!-- Logout Confirmation -->
    <script>
      function confirmLogout() {
        Swal.fire({
          title: 'Are you sure?',
          text: 'Do you want to log out of your account?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, log out',
          cancelButtonText: 'Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = 'logout.php';
          }
        });
      }
    </script>


    </body>

    </html>
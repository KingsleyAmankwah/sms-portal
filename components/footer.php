    <footer class="footer footer-black footer-white">
          <div class="container-fluid">
            <div class="row">
              <nav class="footer-nav">
                <ul>
                  <li>
                    <a href="#" target="_blank"
                      > Inc</a
                    >
                  </li>
                  <li>
                    <a href="#" target="_blank"
                      >Blog</a
                    >
                  </li>
                  <li>
                    <a
                      href="#"
                      target="_blank"
                      >Licenses</a
                    >
                  </li>
                </ul>
              </nav>
              <div class="credits ml-auto">
                <span class="copyright">
                  ©
                  <script>
                    document.write(new Date().getFullYear());
                  </script>
                  , made with <i class="fa fa-heart heart"></i> by  Inc Team
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


    <!-- Chart JS -->
    <script src="../assets/js/plugins/chartjs.min.js"></script>
    <!--  Notifications Plugin    -->
    <script src="../assets/js/plugins/bootstrap-notify.js"></script>
    <!-- Control Center for Now Ui Dashboard: parallax effects, scripts for the example pages etc -->
    <script
      src="../assets/js/paper-dashboard.min.js?v=2.0.1"
      type="text/javascript"
    ></script>
    <!-- Paper Dashboard DEMO methods, don't include it in your project! -->
    <script src="../assets/demo/demo.js"></script>
    <!-- DataTables -->
    <script src="../assets/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="../assets/dataTables/js/dataTables.bootstrap.min.js"></script>
    <script src="../assets/dataTables/js/dataTables.bootstrap.js"></script>
    <script>
      $(document).ready(function () {
        // Javascript method's body can be found in assets/assets-for-demo/js/demo.js
        demo.initChartsPages();
      });
    </script>

    <!-- Data Table Initialize -->
<script>
  $(function () {
    $('#example1').DataTable()
    $('#example2').DataTable({
      'paging'      : true,
      'lengthChange': false,
      'searching'   : false,
      'ordering'    : true,
      'info'        : true,
      'autoWidth'   : false
    })
  })
</script>
  </body>
</html>
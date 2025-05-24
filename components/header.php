<?php
session_start();
require_once '../core/app-config.php';
require_once '../core/extensions/classes.php';

use SMSPortalExtensions\UIActions;

// Check if user is logged in
if (!isset($_SESSION['USER_ID'])) {
    $_SESSION['status'] = "Unauthorized access, please log in to continue";
    $_SESSION['status_code'] = "error";
    header('Location: ' . INDEX_PAGE);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/x-icon" href="../assets/img/teksed-logo.png" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title><?php echo isset($pageTitle) ? $pageTitle : 'SMS Portal'; ?></title>
    <meta
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no"
      name="viewport"
    />
    <!--     Fonts and icons     -->
    <link
      href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200"
      rel="stylesheet"
    />
    <link
      href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css"
      rel="stylesheet"
    />
    <!-- CSS Files -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../assets/css/paper-dashboard.css?v=2.0.1" rel="stylesheet" />

      	<!-- DataTables -->
    <link rel="stylesheet" href="../assets/dataTables/css/dataTables.bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/dataTables/css/dataTables.bootstrap.css" />
  </head>
  <body class="">
 <div class="wrapper">
    <?php include_once 'sidebar.php' ?>
      <div class="main-panel">
        <!-- Navbar -->
        <nav
          class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent"
        >
          <div class="container-fluid">
            <div class="navbar-wrapper">
              <div class="navbar-toggle">
                <button type="button" class="navbar-toggler">
                  <span class="navbar-toggler-bar bar1"></span>
                  <span class="navbar-toggler-bar bar2"></span>
                  <span class="navbar-toggler-bar bar3"></span>
                </button>
              </div>
              <a class="navbar-brand" href="javascript:;"> SMS Portal</a>
            </div>
            <button
              class="navbar-toggler"
              type="button"
              data-toggle="collapse"
              data-target="#navigation"
              aria-controls="navigation-index"
              aria-expanded="false"
              aria-label="Toggle navigation"
            >
              <span class="navbar-toggler-bar navbar-kebab"></span>
              <span class="navbar-toggler-bar navbar-kebab"></span>
              <span class="navbar-toggler-bar navbar-kebab"></span>
            </button>
            <div
              class="collapse navbar-collapse justify-content-end"
              id="navigation"
            >
             
              <ul class="navbar-nav">
                <li class="nav-item btn-rotate dropdown">
                  <a
                    class="nav-link dropdown-toggle"
                    href="http://example.com"
                    id="navbarDropdownMenuLink"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                  >
                    <i class="nc-icon nc-single-02"></i>
                    <p>
                      <span class="d-lg-none d-md-block">Some Actions</span>
                    </p>
                  </a>
                  <div
                    class="dropdown-menu dropdown-menu-right"
                    aria-labelledby="navbarDropdownMenuLink"
                  >
                    <a class="dropdown-item" href="#">Account Settings</a>
                    <a class="dropdown-item" href="#">Contact Support Team</a>
                    <a class="dropdown-item" href="#" onclick="confirmLogout()">Log Out</a>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </nav>
<?php
            if (isset($_SESSION['status'])) {
                echo UIActions::showAlert(
                    $_SESSION['status_code'] === 'error' ? 'Error' : 'Success',
                    $_SESSION['status'],
                    $_SESSION['status_code']
                );
                unset($_SESSION['status'], $_SESSION['status_code']);
            }
?>
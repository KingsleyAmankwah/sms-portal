<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
      <div class="sidebar" data-color="white" data-active-color="danger">
        <div class="logo">
          <a href="https://www.creative-tim.com" class="simple-text logo-mini">
            <div class="logo-image-small">
              <img src="../assets/img/logo-small.png" alt="" />
            </div>
          </a>
          <a
            href="https://www.creative-tim.com"
            class="simple-text logo-normal"
          >
            SMS Portal
 
          </a>
        </div>
        <div class="sidebar-wrapper">
          <ul class="nav">
            <li class="<?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">
              <a href="./dashboard.php">
                <i class="nc-icon nc-bank"></i>
                <p>Dashboard</p>
              </a>
            </li>

            <li class="<?= ($currentPage == 'contact-list.php') ? 'active' : '' ?>">
              <a href="./contact-list.php">
                <i class="nc-icon nc-single-02"></i>
                <p>Contact List</p>
              </a>
            </li>

            <li class="<?= ($currentPage == 'upload-contacts.php') ? 'active' : '' ?>">
              <a href="./upload-contacts.php">
                 <i class="nc-icon nc-cloud-upload-94"></i>
                <p>Upload Contacts</p>
              </a>
            </li>
            <li class="<?= ($currentPage == 'send-sms.php') ? 'active' : '' ?>">
              <a href="./send-sms.php">
                <i class="nc-icon nc-send"></i>
                <p>Send SMS</p>
              </a>
            </li>
        
            <li class="active-pro">
              <a href="#"  onclick="confirmLogout()">
                <i class="nc-icon nc-spaceship"></i>
                <p>Log Out</p>
              </a>
            </li>
          </ul>
        </div>
      </div>
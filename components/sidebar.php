<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" data-color="white" data-active-color="danger">
  <div class="logo">
    <a href="https://www.teksed.com" class="simple-text logo-mini">
      <div class="logo-image-small">
        <img src="../assets/img/teksed-logo.png" alt="" />
      </div>
    </a>
    <a
      href="https://www.teksed.com"
      class="simple-text logo-normal">
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

        <?php if ($_SESSION['USER_ROLE'] === 'admin'): ?>
        <li class="<?= ($currentPage == 'manage-users.php') ? 'active' : '' ?>">
          <a href="./manage-users.php">
            <i class="nc-icon nc-badge"></i>
            <p>Manage Users</p>
          </a>
        </li>

        <li class="<?= ($currentPage == 'manage-sender-ids.php') ? 'active' : '' ?>">
          <a href="./manage-sender-ids.php">
            <i class="nc-icon nc-email-85"></i>
            <p>Manage Sender IDs</p>
          </a>
        </li>

        <li class="<?= ($currentPage == 'security-monitoring.php') ? 'active' : '' ?>">
          <a href="./security-monitoring.php">
            <i class="nc-icon nc-lock-circle-open"></i>
            <p>Security Monitoring</p>
        </a>
  </li>
      <?php endif; ?>

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

      <li class="<?= ($currentPage == 'groups.php') ? 'active' : '' ?>">
        <a href="./groups.php">
          <i class="nc-icon nc-badge"></i>
          <p>Manage Groups</p>
        </a>
      </li>

      <li class="<?= ($currentPage == 'send-sms.php') ? 'active' : '' ?>">
        <a href="./send-sms.php">
          <i class="nc-icon nc-send"></i>
          <p>Send SMS</p>
        </a>
      </li>

      <li class="<?= ($currentPage == 'sms-logs.php') ? 'active' : '' ?>">
        <a href="./sms-logs.php">
          <i class="nc-icon nc-bullet-list-67"></i>
          <p>SMS Logs</p>
        </a>
      </li>

      <li class="<?= ($currentPage == 'account-settings.php') ? 'active' : '' ?>">
        <a href="./account-settings.php">
          <i class="nc-icon nc-settings-gear-65"></i>
          <p>Account Settings</p>
        </a>
      </li>

      <?php if ($_SESSION['USER_ROLE'] === 'client'): ?>
      <li class="<?= ($currentPage == 'contact-support.php') ? 'active' : '' ?>">
        <a href="./contact-support.php">
          <i class="nc-icon nc-headphones"></i>
          <p>Contact Support</p>
        </a>
      </li>
         <?php endif; ?>

      <li class="active-pro">
        <a href="#" onclick="confirmLogout()">
          <i class="nc-icon nc-button-power"></i>
          <p>Log Out</p>
        </a>
      </li>
    </ul>
  </div>
</div>
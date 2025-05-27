<?php
$pageTitle = 'Contact Support';
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Ensure user is logged in
if (!isset($_SESSION['USER_ID'])) {
  $_SESSION['status'] = "Please log in to contact support";
  $_SESSION['status_code'] = "error";
  header('Location: ' . INDEX_PAGE);
  exit;
}

// Fetch user details
$conn = MySQLDatabase::createConnection();
$user = null;
if ($conn) {
  $result = MySQLDatabase::sqlSelect(
    $conn,
    'SELECT username, email FROM users WHERE id = ?',
    'i',
    $_SESSION['USER_ID']
  );
  if ($result) {
    $user = $result->fetch_assoc();
    $result->free_result();
  }
  $conn->close();
}



// Generate CSRF token
$csrf_token = Authentication::createToken();
$pageTitle = 'Contact Support';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-8 col-md-offset-2">
      <div class="card">
        <div class="card-header">
          <h4 class="card-title">Contact Support</h4>
        </div>
        <div class="card-body">
          <form id="contact-support-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="send_support_email">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" class="form-control" id="name" name="name"
                value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" class="form-control" id="email" name="email"
                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
              <label for="subject">Subject</label>
              <input type="text" class="form-control" id="subject" name="subject"
                placeholder="Enter subject" required>
            </div>
            <div class="form-group">
              <label for="message">Message</label>
              <textarea class="form-control" id="message" name="message" rows="5"
                placeholder="Describe your issue" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-fill btn-block">
              <span id="submit-btn-text">Send Support Request</span>
              <span class="spinner-border spinner-border-sm hidden" id="submit-spinner"></span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../components/footer.php'; ?>

<style>
  .card {
    padding: 20px;
  }

  .btn-primary {
    background-color: #390546;
    border-color: #390546;
  }

  .btn-primary:hover {
    background-color: #4b0a5e;
    border-color: #4b0a5e;
  }

  .hidden {
    display: none;
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contact-support-form');
    const button = form.querySelector('button[type="submit"]');
    const submitText = document.getElementById('submit-btn-text');
    const spinner = document.getElementById('submit-spinner');

    form.addEventListener('submit', e => {
      e.preventDefault();

      button.disabled = true;
      submitText.textContent = 'Sending...';
      spinner.classList.remove('hidden');

      const formData = new FormData(form);
      fetch('../controllers/process_support.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          button.disabled = false;
          submitText.textContent = 'Send Support Request';
          spinner.classList.add('hidden');

          Swal.fire({
            title: data.status_code === 'success' ? 'Success' : 'Error',
            text: data.message,
            icon: data.status_code,
            confirmButtonText: 'OK'
          }).then(() => {
            if (data.status_code === 'success') {
              form.reset();
              form.querySelector('#name').value = '<?php echo htmlspecialchars($user['username'] ?? ''); ?>';
              form.querySelector('#email').value = '<?php echo htmlspecialchars($user['email'] ?? ''); ?>';
            }
          });
        })
        .catch(error => {
          button.disabled = false;
          submitText.textContent = 'Send Support Request';
          spinner.classList.add('hidden');

          Swal.fire({
            title: 'Error',
            text: 'Failed to send support request',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        });
    });
  });
</script>
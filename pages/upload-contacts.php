<?php 
  include '../components/header.php'
?>
      <div class="content">
        <div class="main-content flex-grow-1">
            <div class="d-flex justify-content-between mb-4">
                <h2>Import Contacts</h2>
            </div>
            <div class="card p-4">
                <h5>Upload Contact File</h5>
                <p>Import contacts from a CSV or Excel file</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area">

                        <p>Drag and drop your file here or <a href="#">browse</a></p>
                        <i class="nc-icon nc-cloud-upload-94"></i>
                        <input type="file" id="contact_file" name="contact_file" accept=".csv,.xlsx,.xls" style="display:none;" onchange="$('#file-name').text(this.files[0].name)">
                        <p id="file-name"></p>
                        <small>Supports CSV and Excel files</small>
                    </div>
                    <p><strong>File Format Requirements:</strong></p>
                    <ul>
                        <li>File must be in CSV or Excel (.xlsx, .xls) format</li>
                        <li>Must contain at least two columns: Name and Phone Number</li>
                        <li>Phone numbers should include country code (e.g., +233)</li>
                        <li>Optional columns: Email, Group, Company, Notes</li>
                    </ul>
                    <button type="submit" class="btn btn-primary w-100">Import Contacts</button>
                </form>
            </div>
        </div>
      </div>
<?php
 include_once '../components/footer.php'
 
 ?>

      <style>
        .main-content {
            padding: 20px;
        }
        .upload-area {
            border: 2px dashed #ccc;
            padding: 40px;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
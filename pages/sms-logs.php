<?php
$pageTitle = 'SMS Logs';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Initialize variables
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Generate CSRF token
$csrf_token = Authentication::createToken();

// Fetch logs with filtering and pagination
$conn = MySQLDatabase::createConnection();
$logs = [];
$totalLogs = 0;

if ($conn) {
    try {
        // Build query conditions
        $conditions = ['user_id = ?'];
        $params = ['i', $_SESSION['USER_ID']];

        if (!empty($search)) {
            $conditions[] = '(phone_number LIKE ? OR message LIKE ?)';
            $params[0] .= 'ss';
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($status)) {
            $conditions[] = 'status = ?';
            $params[0] .= 's';
            $params[] = $status;
        }

        if (!empty($dateFrom)) {
            $conditions[] = 'DATE(sent_at) >= ?';
            $params[0] .= 's';
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $conditions[] = 'DATE(sent_at) <= ?';
            $params[0] .= 's';
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total records
        $countQuery = "SELECT COUNT(*) as total FROM sms_logs WHERE $whereClause";
        $result = MySQLDatabase::sqlSelect($conn, $countQuery, $params[0], ...array_slice($params, 1));
        if ($result) {
            $totalLogs = $result->fetch_assoc()['total'];
            $result->free_result();
        }

        // Calculate pagination
        $totalPages = ceil($totalLogs / $itemsPerPage);
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        // Fetch logs
        $query = "SELECT id, phone_number, message, sent_at, status, error_message
                 FROM sms_logs
                 WHERE $whereClause
                 ORDER BY sent_at DESC
                 LIMIT ? OFFSET ?";

        $params[0] .= 'ii';
        $params[] = $itemsPerPage;
        $params[] = $offset;

        $result = MySQLDatabase::sqlSelect($conn, $query, $params[0], ...array_slice($params, 1));
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $result->free_result();
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error fetching logs: " . $e->getMessage();
        $_SESSION['status_code'] = "error";
    } finally {
        $conn->close();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">SMS Logs</h4>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="search"
                                        placeholder="Search phone or message..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <select class="form-control" name="status">
                                        <option value="">All Status</option>
                                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <input type="date" class="form-control" name="date_from"
                                        value="<?php echo htmlspecialchars($dateFrom); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <input type="date" class="form-control" name="date_to"
                                        value="<?php echo htmlspecialchars($dateTo); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="sms-logs.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Logs Table -->
                    <div class="">
                        <div id="loading-indicator" class="text-center py-4 <?php echo (!empty($logs)) ? 'd-none' : ''; ?>">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading SMS logs...</p>
                        </div>
                        <table class="table" id="logs-table">
                            <thead>
                                <tr>
                                    <th>Sn.</th>
                                    <th>Phone Number</th>
                                    <th>Message</th>
                                    <th>Sent At</th>
                                    <th>Status</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center p-4">
                                            <?php if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo)): ?>
                                                <div class="empty-state">
                                                    <i class="nc-icon nc-zoom-split text-muted mb-3" style="font-size: 2em;"></i>
                                                    <p class="text-muted">No matching logs found for your filter or search.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <i class="nc-icon nc-chat-33 text-muted mb-3" style="font-size: 2em;"></i>
                                                    <p class="text-muted">No SMS found. Send messages to get started.</p>
                                                    <a href="send-sms.php" class="btn btn-primary btn-sm mt-2">Send SMS</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rowNumber = ($page - 1) * $itemsPerPage + 1; ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php echo $rowNumber++; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)) .
                                                    (strlen($log['message']) > 50 ? '...' : ''); ?></td>
                                            <td><?php
                                                $date = new DateTimeImmutable($log['sent_at']);
                                                echo $date->format('D, M j, Y \a\t g:i A');
                                                ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['status'] === 'success' ?
                                                                                'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['error_message'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php
                                // Calculate pagination range
                                $range = 2; // Number of pages to show on each side of current page
                                $startPage = max(1, $page - $range);
                                $endPage = min($totalPages, $page + $range);

                                // Build query parameters
                                $queryParams = http_build_query([
                                    'search' => $search,
                                    'status' => $status,
                                    'date_from' => $dateFrom,
                                    'date_to' => $dateTo
                                ]);
                                ?>

                                <!-- Previous button -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $queryParams; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- First page -->
                                <?php if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&<?php echo $queryParams; ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Page numbers -->
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $queryParams; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Last page -->
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo $queryParams; ?>">
                                            <?php echo $totalPages; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next button -->
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $queryParams; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../components/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loadingIndicator = document.getElementById('loading-indicator');
        const logsTable = document.getElementById('logs-table');
        const filterForm = document.querySelector('form');

        function showLoading() {
            loadingIndicator.classList.remove('d-none');
            logsTable.classList.add('d-none');
        }

        function hideLoading() {
            loadingIndicator.classList.add('d-none');
            logsTable.classList.remove('d-none');
        }

        // Handle form submission
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            showLoading();

            const formData = new FormData(filterForm);
            const queryString = new URLSearchParams(formData).toString();

            window.location.href = `${filterForm.action}?${queryString}`;
        });

        // Show loading on page navigation
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', () => showLoading());
        });

        if (document.querySelectorAll('#logs-table tbody tr').length > 0) {
            hideLoading();
        }
    });
</script>

<style>
    .badge {
        padding: 5px 10px;
    }

    .btn-primary {
        background-color: #390546;
        border-color: #390546;
    }

    .btn-primary:hover {
        background-color: #4b0a5e !important;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-danger {
        background-color: #dc3545 !important;
    }

    .page-item.active .page-link {
        background-color: #390546;
        border-color: #390546;
    }

    .page-link {
        color: #390546;
    }

    .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    #loading-indicator {
        position: relative;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .table td {
        vertical-align: middle;
    }

    .table.d-none {
        opacity: 0;
    }

    .table {
        transition: opacity 0.3s ease-in-out;
    }

    .table:not(.d-none) {
        opacity: 1;
    }

    .page-item.disabled .page-link {
        color: #6c757d;
        pointer-events: none;
        background-color: #fff;
        border-color: #dee2e6;
    }
</style>
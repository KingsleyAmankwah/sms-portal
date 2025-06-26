<?php
$pageTitle = 'Security Logs';
require_once '../core/admin-check.php';
include_once '../components/header.php';
verifyAdminAccess();

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
        $conditions = [];
        $params = [''];

        if (!empty($search)) {
            $conditions[] = '(login_identifier LIKE ? OR ip_address LIKE ?)';
            $params[0] .= 'ss';
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($status)) {
            $conditions[] = 'll.status = ?';
            $params[0] .= 's';
            $params[] = $status;
        }

        if (!empty($dateFrom)) {
            $conditions[] = 'DATE(attempted_at) >= ?';
            $params[0] .= 's';
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $conditions[] = 'DATE(attempted_at) <= ?';
            $params[0] .= 's';
            $params[] = $dateTo;
        }

        $whereClause = $conditions ? implode(' AND ', $conditions) : '1';

        // Count total records
        $countQuery = "SELECT COUNT(*) as total FROM login_logs ll WHERE $whereClause";
        $result = MySQLDatabase::sqlSelect($conn, $countQuery, $params[0], ...array_slice($params, 1));
        if ($result) {
            $totalLogs = $result->fetch_assoc()['total'];
            $result->free_result();
        }

        // Calculate pagination
        $totalPages = ceil($totalLogs / $itemsPerPage);
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        // Fetch logs with user details for admin
        $query = "SELECT
                    ll.id,
                    ll.login_identifier,
                    ll.status,
                    ll.ip_address,
                    ll.attempted_at,
                    ll.user_agent,
                    u.username
                 FROM login_logs ll
                 LEFT JOIN users u ON ll.user_id = u.id
                 WHERE $whereClause
                 ORDER BY attempted_at DESC
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
                    <h4 class="card-title">Security Logs</h4>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="search"
                                        placeholder="Search email or IP..."
                                        value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <select class="form-control" name="status">
                                        <option value="">All Status</option>
                                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                                        <option value="invalid_password" <?php echo $status === 'invalid_password' ? 'selected' : ''; ?>>Invalid Password</option>
                                        <option value="user_not_found" <?php echo $status === 'user_not_found' ? 'selected' : ''; ?>>User Not Found</option>
                                        <option value="account_locked" <?php echo $status === 'account_locked' ? 'selected' : ''; ?>>Account Locked</option>
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
                                <a href="security-monitoring.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Logs Table -->
                    <div class="">
                        <div id="loading-indicator" class="text-center py-4 <?php echo (!empty($logs)) ? 'd-none' : ''; ?>">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading login logs...</p>
                        </div>
                        <table class="table" id="logs-table">
                            <thead>
                                <tr>
                                    <th>Sn.</th>
                                    <?php if (isset($_SESSION['IS_ADMIN']) && $_SESSION['IS_ADMIN']): ?>
                                        <th>Username</th>
                                    <?php endif; ?>
                                    <th>Login Identifier</th>
                                    <th>IP Address</th>
                                    <th>Attempted At</th>
                                    <th>Status</th>
                                    <th>User Agent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="<?php echo (isset($_SESSION['IS_ADMIN']) && $_SESSION['IS_ADMIN']) ? '7' : '6'; ?>" class="text-center p-4">
                                            <?php if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo)): ?>
                                                <div class="empty-state">
                                                    <i class="nc-icon nc-zoom-split text-muted mb-3" style="font-size: 2em;"></i>
                                                    <p class="text-muted">No matching logs found for your filter or search.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <i class="nc-icon nc-lock-circle-open text-muted mb-3" style="font-size: 2em;"></i>
                                                    <p class="text-muted">No login attempts found.</p>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rowNumber = ($page - 1) * $itemsPerPage + 1; ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $rowNumber++; ?></td>
                                            <?php if (isset($_SESSION['IS_ADMIN']) && $_SESSION['IS_ADMIN']): ?>
                                                <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($log['login_identifier']); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td>
                                                <?php
                                                $date = new DateTimeImmutable($log['attempted_at']);
                                                echo $date->format('D, M j, Y \a\t g:i A');
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php
                                                                            echo $log['status'] === 'success' ? 'success' : (in_array($log['status'], ['invalid_password', 'user_not_found']) ? 'warning' : 'danger');
                                                                            ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="user-agent-tooltip" data-toggle="tooltip" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                    <?php echo htmlspecialchars(substr($log['user_agent'], 0, 20)) . (strlen($log['user_agent']) > 20 ? '...' : ''); ?>
                                                </span>
                                            </td>
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
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php
                                                                                                    echo urlencode($search); ?>&status=<?php
                                                                                                                                        echo urlencode($status); ?>&date_from=<?php
                                                                                                                                                                                echo urlencode($dateFrom); ?>&date_to=<?php
                                                                                                                                                                                                                        echo urlencode($dateTo); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
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
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

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
        text-transform: capitalize;
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

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
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

    .user-agent-tooltip {
        cursor: help;
        border-bottom: 1px dotted #999;
    }
</style>
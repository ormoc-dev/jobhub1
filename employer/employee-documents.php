<?php
include '../config.php';
requireRole('employer');

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// List applications with filters + pagination (scales when there are many applicants)
$searchQ = trim($_GET['q'] ?? '');
$filterStatus = $_GET['app_status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 15);
$allowedPerPage = [10, 15, 25, 50];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}
$allowedFilterStatus = ['', 'pending', 'reviewed', 'accepted', 'rejected'];
if (!in_array($filterStatus, $allowedFilterStatus, true)) {
    $filterStatus = '';
}

$whereClause = 'WHERE jp.company_id = ?';
$queryParams = [$company['id']];

if ($searchQ !== '') {
    $whereClause .= ' AND (ep.first_name LIKE ? OR ep.last_name LIKE ? OR CONCAT(TRIM(ep.first_name), \' \', TRIM(IFNULL(ep.last_name, \'\'))) LIKE ? OR u.email LIKE ? OR jp.title LIKE ?)';
    $like = '%' . $searchQ . '%';
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
}

if ($filterStatus !== '') {
    $whereClause .= ' AND ja.status = ?';
    $queryParams[] = $filterStatus;
}

$baseFrom = 'FROM job_applications ja
             JOIN employee_profiles ep ON ja.employee_id = ep.id
             JOIN users u ON ep.user_id = u.id
             JOIN job_postings jp ON ja.job_id = jp.id';

$countStmt = $pdo->prepare("SELECT COUNT(*) $baseFrom $whereClause");
$countStmt->execute($queryParams);
$totalApplications = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalApplications / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

/* LIMIT/OFFSET must be literals: bound placeholders are quoted under emulated prepares and break MariaDB. */
$limitSql = (int) $perPage;
$offsetSql = (int) $offset;

$selectList = 'SELECT ja.*, ja.resume, ja.id_document, ja.tor_document, ja.employment_certificate, ja.seminar_certificate, ja.cover_letter, ja.certificate_of_attachment, ja.certificate_of_reports, ja.certificate_of_good_standing, ja.applied_date, ja.status as application_status,
                              ep.first_name, ep.last_name, ep.document1, ep.document2,
                              u.email, u.username,
                              jp.title as job_title, jp.id as job_id';

$sql = "$selectList
 $baseFrom
                       $whereClause
                       ORDER BY ja.applied_date DESC
                       LIMIT {$limitSql} OFFSET {$offsetSql}";

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$applications = $stmt->fetchAll();

$fsBase = realpath(__DIR__ . '/..');
if ($fsBase === false) {
    $fsBase = __DIR__ . '/..';
}

/**
 * @return list<array{label:string,path:string,type:?string,verify:bool}>
 */
function employeeDocuments_docDefinitions(): array
{
    return [
        ['cover_letter', 'Cover Letter', 'cover_letter'],
        ['resume', 'Resume / CV', 'resume'],
        ['id_document', 'ID Document', 'id_document'],
        ['tor_document', 'TOR / Transcript', 'tor_document'],
        ['employment_certificate', 'Employment Certificate', 'employment_certificate'],
        ['seminar_certificate', 'Seminar / Training Certificate', 'seminar_certificate'],
        ['certificate_of_attachment', 'Certificate of Attachment', 'certificate_of_attachment'],
        ['certificate_of_reports', 'Certificate of Reports', 'certificate_of_reports'],
        ['certificate_of_good_standing', 'Certificate of Good Standing', 'certificate_of_good_standing'],
    ];
}

/**
 * @return list<array{label:string,path:string,type:?string,verify:bool,exists:bool,ext:string,is_image:bool,is_pdf:bool,web_path:string}>
 */
function employeeDocuments_collectDocs(array $app, string $fsBase): array
{
    $items = [];
    foreach (employeeDocuments_docDefinitions() as [$field, $label, $type]) {
        $path = trim($app[$field] ?? '');
        if ($path === '') {
            continue;
        }
        $fullFs = $fsBase . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $exists = is_file($fullFs);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
        $is_pdf = $ext === 'pdf';
        $items[] = [
            'label' => $label,
            'path' => $path,
            'type' => $type,
            'verify' => true,
            'exists' => $exists,
            'ext' => $ext,
            'is_image' => $is_image,
            'is_pdf' => $is_pdf,
            'web_path' => '../' . $path,
        ];
    }

    if (!empty($app['document1'])) {
        $path = $app['document1'];
        $fullFs = $fsBase . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $exists = is_file($fullFs);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
        $is_pdf = $ext === 'pdf';
        $items[] = [
            'label' => 'Profile — Resume / CV',
            'path' => $path,
            'type' => null,
            'verify' => false,
            'exists' => $exists,
            'ext' => $ext,
            'is_image' => $is_image,
            'is_pdf' => $is_pdf,
            'web_path' => '../' . $path,
        ];
    }

    if (!empty($app['document2'])) {
        $path = $app['document2'];
        $fullFs = $fsBase . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $exists = is_file($fullFs);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
        $is_pdf = $ext === 'pdf';
        $items[] = [
            'label' => 'Profile — Other document',
            'path' => $path,
            'type' => null,
            'verify' => false,
            'exists' => $exists,
            'ext' => $ext,
            'is_image' => $is_image,
            'is_pdf' => $is_pdf,
            'web_path' => '../' . $path,
        ];
    }

    return $items;
}

function employeeDocuments_statusClass(string $status): string
{
    switch ($status) {
        case 'accepted':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'reviewed':
            return 'info';
        default:
            return 'secondary';
    }
}

function employeeDocuments_page_url(int $pageNum, string $searchQ, string $filterStatus, int $perPage): string
{
    $p = ['page' => $pageNum, 'per_page' => $perPage];
    if ($searchQ !== '') {
        $p['q'] = $searchQ;
    }
    if ($filterStatus !== '') {
        $p['app_status'] = $filterStatus;
    }

    return 'employee-documents.php?' . http_build_query($p);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employment Records - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
    <style>
        .records-page .records-intro {
            color: var(--gray-500);
            font-size: 0.9375rem;
            margin: 0;
        }

        .records-toolbar {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .records-toolbar .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray-500);
            margin-bottom: 0.35rem;
        }

        .records-summary {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .records-accordion .accordion-item {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 0.75rem;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .records-accordion .accordion-button {
            padding: 1rem 1.25rem;
            font-size: 0.9375rem;
            background: #f8fafc;
            color: var(--gray-800);
            box-shadow: none;
        }

        .records-accordion .accordion-button:not(.collapsed) {
            background: #eff6ff;
            color: #1e3a8a;
            border-bottom: 1px solid #bfdbfe;
        }

        .records-accordion .accordion-button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .records-accordion .accordion-button::after {
            flex-shrink: 0;
        }

        .records-acc-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
            width: 100%;
            text-align: left;
        }

        .records-acc-name {
            font-weight: 700;
            color: #0f172a;
            min-width: 0;
        }

        .records-acc-meta {
            font-size: 0.8125rem;
            color: var(--gray-600);
        }

        .records-acc-badges {
            margin-left: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }

        .records-docs-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-500);
            font-weight: 600;
            border-bottom-width: 1px;
        }

        .records-docs-table td {
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .records-docs-table .doc-preview {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .records-filename {
            max-width: 220px;
        }

        @media (min-width: 992px) {
            .records-filename {
                max-width: 320px;
            }
        }

        .records-pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content records-page">
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1>
                    <i class="fas fa-file-alt me-2"></i>Hiring Reports
                </h1>
                <p class="records-intro">
                    Applications are grouped by candidate. Use search and filters when the list is long; expand a row to view, download, or verify documents.
                </p>
            </div>
            <a href="applications.php" class="btn btn-outline-primary flex-shrink-0">
                <i class="fas fa-arrow-left me-1"></i>Back to Applications
            </a>
        </div>

        <?php if ($totalApplications === 0 && $searchQ === '' && $filterStatus === ''): ?>
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No applicant documents found yet.
            </div>
        <?php else: ?>
            <form method="get" action="employee-documents.php" class="records-toolbar">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-lg-4">
                        <label for="rec-q" class="form-label">Search</label>
                        <input type="text" class="form-control" name="q" id="rec-q"
                               placeholder="Name, email, or job title…"
                               value="<?php echo htmlspecialchars($searchQ); ?>"
                               autocomplete="off">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label for="rec-status" class="form-label">Application status</label>
                        <select class="form-select" name="app_status" id="rec-status">
                            <option value=""<?php echo $filterStatus === '' ? ' selected' : ''; ?>>All statuses</option>
                            <option value="pending"<?php echo $filterStatus === 'pending' ? ' selected' : ''; ?>>Pending</option>
                            <option value="reviewed"<?php echo $filterStatus === 'reviewed' ? ' selected' : ''; ?>>Reviewed</option>
                            <option value="accepted"<?php echo $filterStatus === 'accepted' ? ' selected' : ''; ?>>Accepted</option>
                            <option value="rejected"<?php echo $filterStatus === 'rejected' ? ' selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="rec-per" class="form-label">Per page</label>
                        <select class="form-select" name="per_page" id="rec-per">
                            <?php foreach ([10, 15, 25, 50] as $n): ?>
                                <option value="<?php echo $n; ?>"<?php echo $perPage === $n ? ' selected' : ''; ?>><?php echo $n; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply
                        </button>
                        <?php if ($searchQ !== '' || $filterStatus !== ''): ?>
                            <a href="employee-documents.php" class="btn btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php
            $showFrom = $totalApplications === 0 ? 0 : $offset + 1;
            $showTo = min($offset + $perPage, $totalApplications);
            ?>
            <p class="records-summary mb-3">
                <i class="fas fa-layer-group me-1"></i>
                <?php if ($totalApplications === 0): ?>
                    No applications match your filters.
                <?php else: ?>
                    Showing <strong><?php echo $showFrom; ?>–<?php echo $showTo; ?></strong> of
                    <strong><?php echo $totalApplications; ?></strong> application<?php echo $totalApplications === 1 ? '' : 's'; ?>
                    (expand a row to load document details)
                <?php endif; ?>
            </p>

            <?php if (empty($applications)): ?>
                <div class="alert alert-light border mb-0">
                    <i class="fas fa-search me-2 text-muted"></i>Try clearing search or choosing “All statuses”.
                </div>
            <?php else: ?>
                <div class="accordion records-accordion" id="recordsAccordion">
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $docs = employeeDocuments_collectDocs($app, $fsBase);
                        $docCount = count($docs);
                        $applicant = htmlspecialchars($app['first_name'] . ' ' . $app['last_name']);
                        $status = $app['application_status'] ?? 'pending';
                        $statusClass = employeeDocuments_statusClass($status);
                        $recCollapseId = 'rec-app-' . (int) $app['id'];
                        $recHeadingId = 'rec-h-' . (int) $app['id'];
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo htmlspecialchars($recHeadingId); ?>">
                                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#<?php echo htmlspecialchars($recCollapseId); ?>"
                                        aria-expanded="false" aria-controls="<?php echo htmlspecialchars($recCollapseId); ?>">
                                    <span class="records-acc-summary">
                                        <span class="records-acc-name">
                                            <i class="fas fa-user text-primary me-2 d-none d-sm-inline" aria-hidden="true"></i><?php echo $applicant; ?>
                                        </span>
                                        <span class="records-acc-meta">
                                            <i class="fas fa-envelope me-1" aria-hidden="true"></i><?php echo htmlspecialchars($app['email']); ?>
                                        </span>
                                        <span class="records-acc-meta">
                                            <i class="fas fa-calendar me-1" aria-hidden="true"></i><?php echo date('M j, Y', strtotime($app['applied_date'])); ?>
                                        </span>
                                        <span class="records-acc-badges">
                                            <span class="badge bg-light text-dark border"><?php echo (int) $docCount; ?> file<?php echo $docCount === 1 ? '' : 's'; ?></span>
                                            <span class="badge bg-primary text-truncate" style="max-width: 10rem;" title="<?php echo htmlspecialchars($app['job_title']); ?>"><?php echo htmlspecialchars($app['job_title']); ?></span>
                                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                        </span>
                                    </span>
                                </button>
                            </h2>
                            <div id="<?php echo htmlspecialchars($recCollapseId); ?>" class="accordion-collapse collapse"
                                 aria-labelledby="<?php echo htmlspecialchars($recHeadingId); ?>" data-bs-parent="#recordsAccordion">
                                <div class="accordion-body p-0 border-top">
                                    <div class="p-3 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                                        <a href="view-application.php?id=<?php echo (int) $app['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt me-1"></i>Open full application
                                        </a>
                                    </div>
                                    <?php if (empty($docs)): ?>
                                        <div class="p-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>No documents uploaded for this application.
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table records-docs-table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th scope="col">Document</th>
                                                        <th scope="col" class="text-center">Preview</th>
                                                        <th scope="col">File</th>
                                                        <th scope="col" class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($docs as $doc): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($doc['label']); ?></strong>
                                                                <?php if (!$doc['exists']): ?>
                                                                    <span class="badge bg-danger ms-1">Missing</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if ($doc['exists'] && $doc['is_image']): ?>
                                                                    <img src="<?php echo htmlspecialchars($doc['web_path']); ?>" alt="" class="doc-preview" loading="lazy">
                                                                <?php elseif ($doc['is_pdf']): ?>
                                                                    <i class="fas fa-file-pdf fa-2x text-danger" aria-hidden="true"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-file fa-2x text-secondary" aria-hidden="true"></i>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="d-inline-block text-truncate records-filename" title="<?php echo htmlspecialchars(basename($doc['path'])); ?>">
                                                                    <?php echo htmlspecialchars(basename($doc['path'])); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-end text-nowrap">
                                                                <?php if ($doc['exists']): ?>
                                                                    <a href="<?php echo htmlspecialchars($doc['web_path']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary" title="View">
                                                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                                                    </a>
                                                                    <a href="<?php echo htmlspecialchars($doc['web_path']); ?>" download class="btn btn-sm btn-outline-primary" title="Download">
                                                                        <i class="fas fa-download" aria-hidden="true"></i>
                                                                    </a>
                                                                    <?php if ($doc['verify'] && $doc['type']): ?>
                                                                        <a href="document-verification.php?application_id=<?php echo (int) $app['id']; ?>&doc_type=<?php echo htmlspecialchars($doc['type']); ?>" class="btn btn-sm btn-success" title="Verify document">
                                                                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Unavailable</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?php
                    $pageLow = max(1, $page - 2);
                    $pageHigh = min($totalPages, $page + 2);
                    ?>
                    <nav class="d-flex flex-column flex-sm-row flex-wrap justify-content-between align-items-center gap-3 mt-4 records-pagination" aria-label="Page navigation">
                        <span class="text-muted small">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
                        <ul class="pagination mb-0 flex-wrap justify-content-center">
                            <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : htmlspecialchars(employeeDocuments_page_url($page - 1, $searchQ, $filterStatus, $perPage)); ?>">Previous</a>
                            </li>
                            <?php for ($p = $pageLow; $p <= $pageHigh; $p++): ?>
                                <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars(employeeDocuments_page_url($p, $searchQ, $filterStatus, $perPage)); ?>"><?php echo (int) $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : htmlspecialchars(employeeDocuments_page_url($page + 1, $searchQ, $filterStatus, $perPage)); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

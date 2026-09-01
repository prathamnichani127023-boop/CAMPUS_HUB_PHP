<?php
// Sanitize input
function clean(string $data): string 
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Flash message
function setFlash(string $type, string $msg): void 
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array 
{
    if (isset($_SESSION['flash'])) 
    {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
function showFlash(): void 
{
    $f = getFlash();
    if (!$f) return;
    $class = match($f['type']) 
    {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    echo "<div class='alert {$class} alert-dismissible fade show' role='alert'>
            {$f['msg']}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}

// Format date nicely
function fmtDate(string $date): string 
{
    return $date ? date('d M Y', strtotime($date)) : '—';
}
function fmtDateTime(string $dt): string 
{
    return $dt ? date('d M Y, h:i A', strtotime($dt)) : '—';
}

// Grade from percentage
function calcGrade(float $pct): string 
{
    return match(true) 
    {
        $pct >= 90 => 'O',
        $pct >= 80 => 'A+',
        $pct >= 70 => 'A',
        $pct >= 60 => 'B+',
        $pct >= 50 => 'B',
        $pct >= 40 => 'C',
        default    => 'F',
    };
}

// Attendance percentage badge
function attBadge(float $pct): string 
{
    $class = $pct >= 75 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger');
    return "<span class='badge {$class}'>{$pct}%</span>";
}

// Pagination helper
function paginate(int $total, int $page, int $perPage = RECORDS_PER_PAGE): array 
{
    $pages = (int)ceil($total / $perPage);
    return [
        'total'  => $total,
        'pages'  => $pages,
        'page'   => max(1, min($page, $pages)),
        'offset' => ($page - 1) * $perPage,
        'limit'  => $perPage,
    ];
}

// File upload helper
function uploadFile(array $file, string $subdir = ''): string|false 
{
    require_once __DIR__ . '/../config/constants.php';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS))   return false;
    if ($file['size'] > MAX_FILE_SIZE)          return false;
    $dir = UPLOAD_PATH . ($subdir ? $subdir . '/' : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = uniqid('', true) . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $fname) ? $fname : false;
}

// Count unread notifications
function unreadCount(mysqli $conn, int $userId): int 
{
    $r = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$userId AND is_read=0");
    return (int)($r->fetch_assoc()['c'] ?? 0);
}

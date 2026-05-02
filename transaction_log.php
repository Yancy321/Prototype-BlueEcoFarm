<?php
require_once 'src/Database.php';
$pdo = Database::getInstance();

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$sort    = in_array($_GET['sort'] ?? '', ['changed_at','operation','table_name']) ? $_GET['sort'] : 'changed_at';
$dir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$total = (int)$pdo->query("SELECT COUNT(*) FROM transaction_logs")->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM transaction_logs ORDER BY {$sort} {$dir} LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute();
$logs = $stmt->fetchAll();

require_once 'includes/header.php';

function sortLink(string $col, string $label, string $current, string $dir): string {
    $newDir = ($col === $current && $dir === 'DESC') ? 'asc' : 'desc';
    $arrow  = $col === $current ? ($dir === 'DESC' ? ' ▼' : ' ▲') : '';
    return "<a href='?sort={$col}&dir={$newDir}&page=1' style='color:#2e7d32;text-decoration:none;'>{$label}{$arrow}</a>";
}
?>

<h1 style="margin-bottom:1.5rem;color:#2e7d32;">Transaction Log</h1>

<div class="card">
    <p style="margin-bottom:1rem;font-size:0.9rem;color:#555;">Total entries: <?= $total ?></p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th><?= sortLink('operation',  'Operation',  $sort, $dir) ?></th>
                <th><?= sortLink('table_name', 'Table',      $sort, $dir) ?></th>
                <th>Record ID</th>
                <th>Snapshot</th>
                <th><?= sortLink('changed_at', 'Changed At', $sort, $dir) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="6">No log entries yet.</td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td><?= htmlspecialchars($log['operation']) ?></td>
                <td><?= htmlspecialchars($log['table_name']) ?></td>
                <td><?= $log['record_id'] ?></td>
                <td style="font-size:0.78rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($log['snapshot'] ?? '') ?>
                </td>
                <td><?= $log['changed_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination" style="margin-top:1rem;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?sort=<?= $sort ?>&dir=<?= strtolower($dir) ?>&page=<?= $i ?>"
               class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

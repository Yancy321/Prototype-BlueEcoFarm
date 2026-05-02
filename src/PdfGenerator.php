<?php

require_once __DIR__ . '/Database.php';

/**
 * PdfGenerator — builds a Stock Movement Report as raw PDF bytes.
 * Uses manual PDF construction (no external library required).
 */
class PdfGenerator
{
    private PDO $pdo;

    // PDF internal state
    private array $objects = [];
    private int   $objCount = 0;
    private array $pages    = [];
    private array $offsets  = [];

    // Page dimensions (A4 in points: 595 x 842)
    private const PW = 595;
    private const PH = 842;
    private const ML = 40;  // margin left
    private const MR = 40;  // margin right
    private const MT = 40;  // margin top
    private const MB = 40;  // margin bottom

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a stock movement report PDF and return raw bytes.
     *
     * @param string   $dateFrom    Start date (YYYY-MM-DD)
     * @param string   $dateTo      End date (YYYY-MM-DD)
     * @param int|null $warehouseId Optional warehouse filter
     * @param int|null $productId   Optional product filter
     * @return string Raw PDF bytes
     */
    public function generateStockMovementReport(
        string $dateFrom,
        string $dateTo,
        ?int $warehouseId = null,
        ?int $productId   = null
    ): string {
        $rows    = $this->queryTransactions($dateFrom, $dateTo, $warehouseId, $productId);
        $summary = $this->buildSummary($rows);
        $filters = $this->buildFilterDescription($dateFrom, $dateTo, $warehouseId, $productId);

        return $this->renderPdf($dateFrom, $dateTo, $filters, $rows, $summary);
    }

    // -------------------------------------------------------------------------
    // Data layer
    // -------------------------------------------------------------------------

    private function queryTransactions(
        string $dateFrom,
        string $dateTo,
        ?int $warehouseId,
        ?int $productId
    ): array {
        $sql = "SELECT sr.transaction_date, p.name AS product_name,
                       sr.warehouse_id, sr.record_type, sr.quantity, sr.notes
                FROM stock_records sr
                JOIN products p ON p.id = sr.product_id
                WHERE sr.is_deleted = 0
                  AND sr.transaction_date BETWEEN :date_from AND :date_to";
        $params = [':date_from' => $dateFrom, ':date_to' => $dateTo];

        if ($warehouseId !== null) {
            $sql .= " AND sr.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouseId;
        }
        if ($productId !== null) {
            $sql .= " AND sr.product_id = :product_id";
            $params[':product_id'] = $productId;
        }

        $sql .= " ORDER BY sr.transaction_date ASC, p.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function buildSummary(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $name = $row['product_name'];
            if (!isset($summary[$name])) {
                $summary[$name] = ['incoming' => 0, 'outgoing' => 0];
            }
            if ($row['record_type'] === 'incoming') {
                $summary[$name]['incoming'] += (int)$row['quantity'];
            } else {
                $summary[$name]['outgoing'] += (int)$row['quantity'];
            }
        }
        return $summary;
    }

    private function buildFilterDescription(
        string $dateFrom,
        string $dateTo,
        ?int $warehouseId,
        ?int $productId
    ): string {
        $parts = ["Date range: {$dateFrom} to {$dateTo}"];

        if ($warehouseId !== null) {
            $wName = $warehouseId === 1 ? 'Farm' : ($warehouseId === 2 ? 'Paranaque' : "Warehouse #{$warehouseId}");
            $parts[] = "Warehouse: {$wName}";
        }
        if ($productId !== null) {
            $stmt = $this->pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $row = $stmt->fetch();
            $pName = $row ? $row['name'] : "Product #{$productId}";
            $parts[] = "Product: {$pName}";
        }

        return implode('  |  ', $parts);
    }

    // -------------------------------------------------------------------------
    // PDF rendering
    // -------------------------------------------------------------------------

    private function renderPdf(
        string $dateFrom,
        string $dateTo,
        string $filters,
        array  $rows,
        array  $summary
    ): string {
        $this->objects  = [];
        $this->objCount = 0;
        $this->pages    = [];
        $this->offsets  = [];

        // Collect all content lines first, then paginate
        $contentLines = $this->buildContentLines($dateFrom, $dateTo, $filters, $rows, $summary);
        $pageStreams  = $this->paginateLines($contentLines);

        // Build PDF objects
        $catalogId  = ++$this->objCount;
        $pagesId    = ++$this->objCount;
        $fontId     = ++$this->objCount;
        $fontBoldId = ++$this->objCount;

        $pageIds = [];
        $streamIds = [];
        $totalPages = count($pageStreams);

        foreach ($pageStreams as $i => $stream) {
            $pageId   = ++$this->objCount;
            $streamId = ++$this->objCount;
            $pageIds[]   = $pageId;
            $streamIds[] = $streamId;
        }

        // Now build object strings
        $this->objects[$catalogId] = "<<\n/Type /Catalog\n/Pages {$pagesId} 0 R\n>>";

        $kidsStr = implode(' 0 R ', $pageIds) . ' 0 R';
        $this->objects[$pagesId] = "<<\n/Type /Pages\n/Kids [{$kidsStr}]\n/Count {$totalPages}\n>>";

        // Helvetica font (standard, no embedding needed)
        $this->objects[$fontId] = "<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n/Encoding /WinAnsiEncoding\n>>";
        $this->objects[$fontBoldId] = "<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Bold\n/Encoding /WinAnsiEncoding\n>>";

        foreach ($pageStreams as $i => $stream) {
            $pageId   = $pageIds[$i];
            $streamId = $streamIds[$i];

            $this->objects[$pageId] = "<<\n/Type /Page\n/Parent {$pagesId} 0 R\n"
                . "/MediaBox [0 0 " . self::PW . " " . self::PH . "]\n"
                . "/Contents {$streamId} 0 R\n"
                . "/Resources <<\n"
                . "  /Font << /F1 {$fontId} 0 R /F2 {$fontBoldId} 0 R >>\n"
                . ">>\n>>";

            $this->objects[$streamId] = $stream; // raw stream bytes
        }

        return $this->assemblePdf($catalogId, $totalPages);
    }

    /**
     * Build all text lines as structured items: [type, text, indent]
     * Types: 'title', 'subtitle', 'header', 'row', 'separator', 'blank', 'summary_header', 'summary_row'
     */
    private function buildContentLines(
        string $dateFrom,
        string $dateTo,
        string $filters,
        array  $rows,
        array  $summary
    ): array {
        $lines = [];

        // Report header info (drawn on every page separately)
        $lines[] = ['type' => 'title',    'text' => 'Blue Eco Farm — Stock Movement Report'];
        $lines[] = ['type' => 'subtitle', 'text' => 'Generated: ' . date('Y-m-d H:i:s')];
        $lines[] = ['type' => 'subtitle', 'text' => $filters];
        $lines[] = ['type' => 'blank'];

        // Table header
        $lines[] = ['type' => 'table_header'];

        if (empty($rows)) {
            $lines[] = ['type' => 'no_data', 'text' => 'No transactions found for the specified filters.'];
        } else {
            foreach ($rows as $row) {
                $wName = $row['warehouse_id'] == 1 ? 'Farm' : 'Paranaque';
                $lines[] = [
                    'type'      => 'table_row',
                    'date'      => $row['transaction_date'],
                    'product'   => $row['product_name'],
                    'warehouse' => $wName,
                    'rtype'     => $row['record_type'],
                    'qty'       => number_format((int)$row['quantity'], 0),
                    'notes'     => $row['notes'] ?? '',
                ];
            }
        }

        $lines[] = ['type' => 'blank'];
        $lines[] = ['type' => 'summary_header'];

        if (!empty($summary)) {
            foreach ($summary as $productName => $totals) {
                $lines[] = [
                    'type'     => 'summary_row',
                    'product'  => $productName,
                    'incoming' => number_format($totals['incoming'], 0),
                    'outgoing' => number_format($totals['outgoing'], 0),
                ];
            }
        } else {
            $lines[] = ['type' => 'no_data', 'text' => 'No summary data available.'];
        }

        return $lines;
    }

    /**
     * Paginate content lines into page streams.
     * Returns array of raw PDF stream strings (one per page).
     */
    private function paginateLines(array $lines): array
    {
        $usableH = self::PH - self::MT - self::MB - 80; // reserve for page header/footer
        $lineH   = 14;
        $titleH  = 20;
        $subH    = 13;
        $tableHH = 16;

        // Estimate height per line type
        $heights = [
            'title'          => $titleH,
            'subtitle'       => $subH,
            'blank'          => 8,
            'table_header'   => $tableHH + 4,
            'table_row'      => $lineH,
            'no_data'        => $lineH,
            'summary_header' => $tableHH + 4,
            'summary_row'    => $lineH,
        ];

        $pages       = [];
        $currentPage = [];
        $currentH    = 0;

        foreach ($lines as $line) {
            $h = $heights[$line['type']] ?? $lineH;
            if ($currentH + $h > $usableH && !empty($currentPage)) {
                $pages[]     = $currentPage;
                $currentPage = [];
                $currentH    = 0;
                // Re-add table header if we were in a table
                $lastType = end($currentPage)['type'] ?? '';
                // Add table header continuation
                if (in_array($line['type'], ['table_row', 'no_data'])) {
                    $currentPage[] = ['type' => 'table_header'];
                    $currentH += $heights['table_header'];
                }
            }
            $currentPage[] = $line;
            $currentH += $h;
        }

        if (!empty($currentPage)) {
            $pages[] = $currentPage;
        }

        $totalPages = count($pages);
        $streams    = [];

        foreach ($pages as $pageNum => $pageLines) {
            $streams[] = $this->buildPageStream($pageLines, $pageNum + 1, $totalPages);
        }

        return $streams;
    }

    /**
     * Build a PDF content stream for one page.
     */
    private function buildPageStream(array $lines, int $pageNum, int $totalPages): string
    {
        $ops = [];
        $y   = self::PH - self::MT; // start from top

        // Draw page border line at top
        $ops[] = $this->drawLine(self::ML, $y - 2, self::PW - self::MR, $y - 2, 0.5);
        $y -= 4;

        $colW = [70, 130, 70, 55, 50, 120]; // Date, Product, Warehouse, Type, Qty, Notes
        $colX = [self::ML];
        for ($i = 1; $i < count($colW); $i++) {
            $colX[] = $colX[$i - 1] + $colW[$i - 1];
        }

        foreach ($lines as $line) {
            switch ($line['type']) {
                case 'title':
                    $ops[] = $this->text($line['text'], self::ML, $y, 14, true);
                    $y -= 20;
                    break;

                case 'subtitle':
                    $ops[] = $this->text($line['text'], self::ML, $y, 9, false, [0.3, 0.3, 0.3]);
                    $y -= 13;
                    break;

                case 'blank':
                    $y -= 8;
                    break;

                case 'table_header':
                    // Background rect
                    $ops[] = $this->fillRect(self::ML, $y - 14, self::PW - self::MR - self::ML, 16, [0.18, 0.49, 0.20]);
                    $headers = ['Date', 'Product', 'Warehouse', 'Type', 'Qty', 'Notes'];
                    foreach ($headers as $i => $h) {
                        $ops[] = $this->text($h, $colX[$i] + 2, $y - 2, 8, true, [1, 1, 1]);
                    }
                    $y -= 20;
                    break;

                case 'table_row':
                    $cells = [
                        $line['date'],
                        $this->truncate($line['product'], 22),
                        $line['warehouse'],
                        $line['rtype'],
                        $line['qty'],
                        $this->truncate($line['notes'], 20),
                    ];
                    $color = $line['rtype'] === 'incoming' ? [0.11, 0.37, 0.13] : [0.55, 0.0, 0.0];
                    foreach ($cells as $i => $cell) {
                        $c = ($i === 3) ? $color : [0.1, 0.1, 0.1];
                        $ops[] = $this->text($cell, $colX[$i] + 2, $y, 8, false, $c);
                    }
                    // Row separator
                    $ops[] = $this->drawLine(self::ML, $y - 3, self::PW - self::MR, $y - 3, 0.2, [0.85, 0.85, 0.85]);
                    $y -= 14;
                    break;

                case 'no_data':
                    $ops[] = $this->text($line['text'], self::ML + 4, $y, 9, false, [0.5, 0.5, 0.5]);
                    $y -= 14;
                    break;

                case 'summary_header':
                    $ops[] = $this->text('Summary', self::ML, $y, 11, true, [0.18, 0.49, 0.20]);
                    $y -= 16;
                    $ops[] = $this->fillRect(self::ML, $y - 12, 350, 14, [0.18, 0.49, 0.20]);
                    foreach (['Product', 'Total Incoming', 'Total Outgoing'] as $i => $h) {
                        $sx = self::ML + $i * 120 + 2;
                        $ops[] = $this->text($h, $sx, $y - 1, 8, true, [1, 1, 1]);
                    }
                    $y -= 18;
                    break;

                case 'summary_row':
                    $ops[] = $this->text($this->truncate($line['product'], 20), self::ML + 2, $y, 8);
                    $ops[] = $this->text($line['incoming'], self::ML + 122, $y, 8, false, [0.11, 0.37, 0.13]);
                    $ops[] = $this->text($line['outgoing'], self::ML + 242, $y, 8, false, [0.55, 0.0, 0.0]);
                    $ops[] = $this->drawLine(self::ML, $y - 3, self::ML + 350, $y - 3, 0.2, [0.85, 0.85, 0.85]);
                    $y -= 14;
                    break;
            }
        }

        // Footer: page number
        $footerY = self::MB - 10;
        $ops[] = $this->drawLine(self::ML, $footerY + 12, self::PW - self::MR, $footerY + 12, 0.5);
        $ops[] = $this->text(
            "Blue Eco Farm Inventory System  |  Page {$pageNum} of {$totalPages}",
            self::ML,
            $footerY,
            8,
            false,
            [0.5, 0.5, 0.5]
        );

        $stream = implode("\n", $ops);
        return $stream;
    }

    // -------------------------------------------------------------------------
    // PDF primitive helpers
    // -------------------------------------------------------------------------

    private function text(
        string $text,
        float  $x,
        float  $y,
        int    $size = 10,
        bool   $bold = false,
        array  $rgb  = [0, 0, 0]
    ): string {
        $font   = $bold ? '/F2' : '/F1';
        $r      = number_format($rgb[0], 3, '.', '');
        $g      = number_format($rgb[1], 3, '.', '');
        $b      = number_format($rgb[2], 3, '.', '');
        $safe   = $this->pdfEscape($text);
        return "BT {$font} {$size} Tf {$r} {$g} {$b} rg {$x} {$y} Td ({$safe}) Tj ET";
    }

    private function drawLine(
        float $x1, float $y1, float $x2, float $y2,
        float $width = 0.5,
        array $rgb   = [0, 0, 0]
    ): string {
        $r = number_format($rgb[0], 3, '.', '');
        $g = number_format($rgb[1], 3, '.', '');
        $b = number_format($rgb[2], 3, '.', '');
        return "{$width} w {$r} {$g} {$b} RG {$x1} {$y1} m {$x2} {$y2} l S";
    }

    private function fillRect(
        float $x, float $y, float $w, float $h,
        array $rgb = [0.9, 0.9, 0.9]
    ): string {
        $r = number_format($rgb[0], 3, '.', '');
        $g = number_format($rgb[1], 3, '.', '');
        $b = number_format($rgb[2], 3, '.', '');
        return "{$r} {$g} {$b} rg {$x} {$y} {$w} {$h} re f";
    }

    private function pdfEscape(string $text): string
    {
        // Remove non-latin characters that can't be encoded in WinAnsiEncoding
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $text;
    }

    private function truncate(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) return $text;
        return mb_substr($text, 0, $maxLen - 1) . '…';
    }

    // -------------------------------------------------------------------------
    // PDF assembly
    // -------------------------------------------------------------------------

    private function assemblePdf(int $catalogId, int $totalPages): string
    {
        $body    = "%PDF-1.4\n";
        $offsets = [];

        foreach ($this->objects as $id => $content) {
            $offsets[$id] = strlen($body);

            if (strpos($content, "\n") !== false && substr($content, 0, 2) !== '<<') {
                // It's a page stream
                $encoded = $content;
                $len     = strlen($encoded);
                $body   .= "{$id} 0 obj\n<< /Length {$len} >>\nstream\n{$encoded}\nendstream\nendobj\n";
            } else {
                $body .= "{$id} 0 obj\n{$content}\nendobj\n";
            }
        }

        // Cross-reference table
        $xrefOffset = strlen($body);
        $count      = count($this->objects) + 1;
        $body      .= "xref\n0 {$count}\n";
        $body      .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($this->objects); $i++) {
            $off   = isset($offsets[$i]) ? $offsets[$i] : 0;
            $body .= str_pad($off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $body .= "trailer\n<< /Size {$count} /Root {$catalogId} 0 R >>\n";
        $body .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $body;
    }
}

<?php
/**
 * ChemHeaven — Admin: Import / Export Products
 *
 * Export: JS fetches /admin/api/export.php and triggers a file download.
 * Import: JS reads a local JSON file and POSTs it to /admin/api/import.php,
 *         then shows results inline — no page reload required.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

send_security_headers();
session_secure_start();
admin_require_auth();

$adminPageTitle = 'Import / Export';
$adminActiveNav = 'import-export';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="admin-page-header">
    <h1>Import / Export</h1>
</div>

<div class="admin-form-grid">

    <!-- ── Export ─────────────────────────────────────────────────────────── -->
    <div class="admin-card">
        <h2>⬇ Export products</h2>
        <p>Download all products (including variants, categories, and tags) as a JSON file.</p>
        <button type="button" class="btn-primary" id="btn-export">Export products.json</button>
        <div id="export-status" class="import-status" style="display:none"></div>
    </div>

    <!-- ── Import ─────────────────────────────────────────────────────────── -->
    <div class="admin-card">
        <h2>⬆ Import products</h2>
        <p>Upload a JSON file following the ChemHeaven product structure. Existing products with matching UUIDs will be <strong>updated</strong>; new ones will be <strong>created</strong>.</p>

        <div class="import-drop-zone" id="drop-zone">
            <p>Drag &amp; drop a <code>.json</code> file here, or</p>
            <label class="btn-secondary" style="cursor:pointer">
                Choose file
                <input type="file" id="import-file" accept=".json,application/json" style="display:none">
            </label>
            <p id="chosen-file" class="text-muted" style="margin-top:8px"></p>
        </div>

        <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
            <button type="button" class="btn-primary" id="btn-import" disabled>Import</button>
            <label class="label-inline" style="font-size:.85rem">
                <input type="checkbox" id="dry-run" checked>
                Dry run (preview only, no DB changes)
            </label>
        </div>

        <div id="import-status" class="import-status" style="display:none"></div>
    </div>

</div>

<!-- JSON structure reference -->
<div class="admin-card" style="margin-top:24px">
    <details>
        <summary style="cursor:pointer;font-weight:700">JSON structure reference</summary>
        <pre class="code-block"><?= h('{
  "products": [
    {
      "id": "uuid-v4",
      "name": "Product Name",
      "slug": "product-slug",
      "description": "Description text",
      "categoryId": "category-uuid",
      "image": "https://example.com/image.jpg",
      "images": ["https://...", "https://..."],
      "tags": ["tag-uuid-1", "tag-uuid-2"],
      "isActive": true,
      "isFeatured": false,
      "sku": "PROD-SKU",
      "createdAt": "2024-01-01T00:00:00Z",
      "updatedAt": "2024-01-01T00:00:00Z",
      "variants": [
        {
          "id": "variant-uuid",
          "productId": "uuid-v4",
          "label": "10g",
          "unit": "10g",
          "price": 29.99,
          "stock": 50,
          "sku": "PROD-SKU-10G",
          "isActive": true
        }
      ],
      "category": {
        "id": "uuid-v4",
        "name": "Category Name",
        "slug": "category-slug",
        "description": "...",
        "image": "...",
        "sortOrder": 0,
        "isActive": true,
        "createdAt": "2024-01-01T00:00:00Z"
      },
      "productTags": [
        {
          "id": "tag-uuid",
          "name": "Tag Name",
          "color": "#ffffff",
          "bgColor": "#000000",
          "tagGroupId": "group-uuid",
          "sortOrder": 0,
          "createdAt": "2024-01-01T00:00:00Z"
        }
      ]
    }
  ]
}') ?></pre>
    </details>
</div>

<script>
(function () {
    'use strict';

    /* ── CSRF token (injected server-side) ──────────────────────────────── */
    var csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;

    /* ── File picker ─────────────────────────────────────────────────────── */
    var fileInput   = document.getElementById('import-file');
    var btnImport   = document.getElementById('btn-import');
    var chosenLabel = document.getElementById('chosen-file');
    var dropZone    = document.getElementById('drop-zone');
    var selectedFile = null;

    function setFile(file) {
        if (!file || file.type !== 'application/json' && !file.name.endsWith('.json')) {
            showStatus('import-status', 'error', 'Please choose a .json file.');
            return;
        }
        selectedFile = file;
        chosenLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        btnImport.disabled = false;
    }

    fileInput.addEventListener('change', function () { if (this.files[0]) setFile(this.files[0]); });

    dropZone.addEventListener('dragover', function (e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', function () { dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
    });

    /* ── Export ──────────────────────────────────────────────────────────── */
    document.getElementById('btn-export').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Exporting…';
        showStatus('export-status', 'info', 'Generating export…');

        fetch('/admin/api/export.php', {
            method: 'GET',
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        })
        .then(function (r) {
            if (!r.ok) throw new Error('Server returned ' + r.status);
            return r.blob();
        })
        .then(function (blob) {
            var url = URL.createObjectURL(blob);
            var a   = document.createElement('a');
            a.href     = url;
            a.download = 'chemheaven-products-' + new Date().toISOString().slice(0,10) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showStatus('export-status', 'success', 'Export downloaded successfully.');
        })
        .catch(function (err) {
            showStatus('export-status', 'error', 'Export failed: ' + err.message);
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = 'Export products.json';
        });
    });

    /* ── Import ──────────────────────────────────────────────────────────── */
    btnImport.addEventListener('click', function () {
        if (!selectedFile) return;
        var btn    = this;
        var dryRun = document.getElementById('dry-run').checked;

        btn.disabled = true;
        btn.textContent = 'Importing…';
        showStatus('import-status', 'info', 'Reading file…');

        var reader = new FileReader();
        reader.onload = function (e) {
            var json;
            try { json = JSON.parse(e.target.result); }
            catch (err) {
                showStatus('import-status', 'error', 'Invalid JSON: ' + err.message);
                btn.disabled = false; btn.textContent = 'Import'; return;
            }

            showStatus('import-status', 'info', 'Sending to server…');

            fetch('/admin/api/import.php?dry_run=' + (dryRun ? '1' : '0'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(json)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    var msg = (dryRun ? '[DRY RUN] ' : '') +
                              'Created: ' + data.created + ', Updated: ' + data.updated +
                              ', Skipped: ' + data.skipped + '.' +
                              (data.errors && data.errors.length ? '\nErrors: ' + data.errors.join('; ') : '');
                    showStatus('import-status', data.errors && data.errors.length ? 'warning' : 'success', msg);
                } else {
                    showStatus('import-status', 'error', data.error || 'Import failed.');
                }
            })
            .catch(function (err) {
                showStatus('import-status', 'error', 'Import failed: ' + err.message);
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Import';
            });
        };
        reader.readAsText(selectedFile);
    });

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function showStatus(id, type, msg) {
        var el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'import-status import-status--' + type;
        el.textContent = msg;
    }
}());
</script>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>

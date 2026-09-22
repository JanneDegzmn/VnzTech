<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';

requireAdminLogin();

/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

if (!hasPermission('manage_rates')) {
    http_response_code(403);
    ?>

    <div class="rates-page">
        <div class="rates-access-denied">

            <div class="access-denied-icon">!</div>

            <div>
                <h2>Access Denied</h2>
                <p>
                    You do not have permission to manage buying rates.
                </p>
            </div>

        </div>
    </div>

    <?php
    exit;
}

$csrfToken = generateCsrfToken();


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$rateTypeFilter = trim($_GET['rate_type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$view = ($_GET['view'] ?? 'active') === 'archived' ? 'archived' : 'active';

$allowedRateTypes = ['phone', 'bagsakan', 'non_code_board'];


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        br.id,
        br.rate_type,
        br.category,
        br.description,
        br.price,
        br.unit,
        br.is_active,
        br.archived_at,
        br.updated_at,
        ua.first_name AS updated_by_first_name,
        ua.last_name AS updated_by_last_name,
        aa.first_name AS archived_by_first_name,
        aa.last_name AS archived_by_last_name
    FROM buying_rates br
    LEFT JOIN admins ua
        ON ua.id = br.updated_by_admin_id
    LEFT JOIN admins aa
        ON aa.id = br.archived_by_admin_id
    WHERE 1 = 1
";

$params = [];

if ($view === 'archived') {
    $sql .= " AND br.archived_at IS NOT NULL ";
} else {
    $sql .= " AND br.archived_at IS NULL ";
}

if ($search !== '') {

    $sql .= "
        AND (
            br.category LIKE ?
            OR br.description LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (
    $rateTypeFilter !== ''
    && in_array($rateTypeFilter, $allowedRateTypes, true)
) {

    $sql .= "
        AND br.rate_type = ?
    ";

    $params[] = $rateTypeFilter;
}

if ($statusFilter === 'active') {

    $sql .= " AND br.is_active = 1 ";

} elseif ($statusFilter === 'inactive') {

    $sql .= " AND br.is_active = 0 ";

}

$sql .= "
    ORDER BY
        br.rate_type ASC,
        br.category ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rates = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Summary Counts (unfiltered totals)
|--------------------------------------------------------------------------
*/

$totalStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(is_active = 1), 0) AS active_count,
        COALESCE(SUM(rate_type = 'phone'), 0) AS phone_count,
        COALESCE(SUM(rate_type = 'bagsakan'), 0) AS bagsakan_count,
        COALESCE(SUM(rate_type = 'non_code_board'), 0) AS non_code_board_count
    FROM buying_rates
    WHERE archived_at IS NULL
");

$summary = $totalStmt->fetch();

$archivedCountStmt = $pdo->query("
    SELECT COUNT(*) AS archived_count
    FROM buying_rates
    WHERE archived_at IS NOT NULL
");

$archivedCount = (int) $archivedCountStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function rateTypeLabel(string $rateType): string
{
    $labels = [
        'phone' => 'Phone',
        'bagsakan' => 'Board Code',
        'non_code_board' => 'Non-Code Board'
    ];

    return $labels[$rateType] ?? ucfirst($rateType);
}

function rateTypeClass(string $rateType): string
{
    $classes = [
        'phone' => 'rate-type-phone',
        'bagsakan' => 'rate-type-bagsakan',
        'non_code_board' => 'rate-type-noncode'
    ];

    return $classes[$rateType] ?? '';
}

function ratePesoAmount($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function rateUpdatedLabel(string $date): string
{
    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return date('M d, Y h:i A', $timestamp);
}

function rateArchivedLabel(?string $date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return date('M d, Y h:i A', $timestamp);
}

?>

<link
    rel="stylesheet"
    href="/VNZ-TECH/assets/css/admin_buying_rates.css"
>


<div class="rates-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="rates-header">

        <div class="rates-heading">

            <div class="rates-kicker">
                VNZ TECH MANAGEMENT
            </div>

            <h1>
                Buying Rates
            </h1>

            <p>
                Manage phone grade and bagsakan/storage buying prices used across VNZ TECH.
            </p>

        </div>


        <button
            type="button"
            class="rates-add-button"
            id="openAddRateModal"
            <?= $view === 'archived' ? 'style="display:none;"' : '' ?>
        >
            + ADD RATE
        </button>

    </div>



    <!-- =====================================================
         TABS
    ====================================================== -->

    <div class="rates-tabs">

        <button
            type="button"
            class="rates-tab <?= $view === 'active' ? 'active' : '' ?>"
            data-view="active"
        >
            RATES
        </button>

        <button
            type="button"
            class="rates-tab <?= $view === 'archived' ? 'active' : '' ?>"
            data-view="archived"
        >
            ARCHIVED
            <?php if ($archivedCount > 0): ?>
                <span class="rates-tab-count"><?= $archivedCount ?></span>
            <?php endif; ?>
        </button>

    </div>



    <!-- =====================================================
         SUMMARY CARDS
    ====================================================== -->

    <div class="rates-summary-row">

        <div class="rates-summary-card">
            <span>TOTAL RATES</span>
            <strong><?= number_format((int) $summary['total_count']) ?></strong>
        </div>

        <div class="rates-summary-card">
            <span>ACTIVE RATES</span>
            <strong><?= number_format((int) $summary['active_count']) ?></strong>
        </div>

        <div class="rates-summary-card">
            <span>PHONE RATES</span>
            <strong><?= number_format((int) $summary['phone_count']) ?></strong>
        </div>

        <div class="rates-summary-card">
            <span>BOARD CODE RATES</span>
            <strong><?= number_format((int) $summary['bagsakan_count']) ?></strong>
        </div>

        <div class="rates-summary-card">
            <span>NON-CODE BOARD RATES</span>
            <strong><?= number_format((int) $summary['non_code_board_count']) ?></strong>
        </div>

    </div>



    <!-- =====================================================
         FILTER BAR
    ====================================================== -->

    <div class="rates-filter-card">

        <div class="rates-search">

            <span class="search-symbol">⌕</span>

            <input
                type="text"
                id="rateSearch"
                placeholder="Search category or description..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >

        </div>


        <select
            id="rateTypeFilterSelect"
            class="rates-filter-select"
        >

            <option value="">All Types</option>
            <option value="phone" <?= $rateTypeFilter === 'phone' ? 'selected' : '' ?>>Phone</option>
            <option value="bagsakan" <?= $rateTypeFilter === 'bagsakan' ? 'selected' : '' ?>>Board Code</option>
            <option value="non_code_board" <?= $rateTypeFilter === 'non_code_board' ? 'selected' : '' ?>>Non-Code Board</option>

        </select>


        <select
            id="rateStatusFilterSelect"
            class="rates-filter-select"
        >

            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>

        </select>


        <button
            type="button"
            id="clearRateFilters"
            class="clear-rates-filters"
        >
            CLEAR
        </button>

    </div>



    <!-- =====================================================
         RATES TABLE
    ====================================================== -->

    <div class="rates-card">

        <div class="rates-card-header">

            <div>
                <h2><?= $view === 'archived' ? 'Archived Rates' : 'Rate List' ?></h2>
                <span id="rateVisibleCount">
                    Showing <?= number_format(count($rates)) ?> rate<?= count($rates) !== 1 ? 's' : '' ?>
                </span>
            </div>

        </div>


        <?php if (empty($rates)): ?>

            <div class="rates-empty">

                <div class="empty-symbol">+</div>

                <h3><?= $view === 'archived' ? 'No archived rates' : 'No buying rates yet' ?></h3>

                <p>
                    <?= $view === 'archived'
                        ? 'Rates you delete from the main list will appear here.'
                        : 'Add a phone grade or bagsakan rate to get started.' ?>
                </p>

            </div>

        <?php else: ?>

            <div class="rates-table-wrapper">

                <table class="rates-table">

                    <thead>
                        <tr>
                            <th>TYPE</th>
                            <th>CATEGORY / DESCRIPTION</th>
                            <th>PRICE</th>
                            <th>STATUS</th>
                            <th><?= $view === 'archived' ? 'ARCHIVED' : 'LAST UPDATED' ?></th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>

                    <tbody id="ratesTableBody">

                    <?php foreach ($rates as $rate): ?>

                        <?php

                        $rateId = (int) $rate['id'];
                        $rateType = $rate['rate_type'];
                        $category = $rate['category'];
                        $description = $rate['description'] ?? '';
                        $price = (float) $rate['price'];
                        $unit = $rate['unit'];
                        $isActive = (bool) $rate['is_active'];

                        $updatedByName = trim(
                            ($rate['updated_by_first_name'] ?? '')
                            . ' '
                            . ($rate['updated_by_last_name'] ?? '')
                        );

                        $searchData = strtolower(
                            $category . ' ' . $description
                        );

                        ?>

                        <tr
                            class="<?= $isActive ? '' : 'rate-inactive' ?>"
                            data-search="<?= htmlspecialchars($searchData) ?>"
                            data-rate-type="<?= htmlspecialchars($rateType) ?>"
                            data-status="<?= $isActive ? 'active' : 'inactive' ?>"
                            data-rate-id="<?= $rateId ?>"
                        >

                            <td>
                                <span class="rate-type-badge <?= rateTypeClass($rateType) ?>">
                                    <?= htmlspecialchars(rateTypeLabel($rateType)) ?>
                                </span>
                            </td>

                            <td>
                                <div class="rate-category-cell">
                                    <strong><?= htmlspecialchars($category) ?></strong>
                                    <?php if ($description !== ''): ?>
                                        <span><?= htmlspecialchars($description) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <div class="rate-price-cell">
                                    <?= ratePesoAmount($price) ?>
                                </div>
                                <div class="rate-unit-cell">
                                    per <?= htmlspecialchars($unit) ?>
                                </div>
                            </td>

                            <td>
                                <span class="rate-status <?= $isActive ? 'rate-status-active' : 'rate-status-inactive' ?>">
                                    <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
                                </span>
                            </td>

                            <td>
                                <div class="rate-updated-cell">
                                    <?php if ($view === 'archived'): ?>

                                        <?php

                                        $archivedByName = trim(
                                            ($rate['archived_by_first_name'] ?? '')
                                            . ' '
                                            . ($rate['archived_by_last_name'] ?? '')
                                        );

                                        ?>

                                        <?= rateArchivedLabel($rate['archived_at']) ?>
                                        <?php if ($archivedByName !== ''): ?>
                                            <br>by <?= htmlspecialchars($archivedByName) ?>
                                        <?php endif; ?>

                                    <?php else: ?>

                                        <?= rateUpdatedLabel($rate['updated_at']) ?>
                                        <?php if ($updatedByName !== ''): ?>
                                            <br>by <?= htmlspecialchars($updatedByName) ?>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <div class="rate-actions-cell">

                                    <?php if ($view === 'archived'): ?>

                                        <button
                                            type="button"
                                            class="rate-restore-button"
                                            data-id="<?= $rateId ?>"
                                        >
                                            RESTORE
                                        </button>

                                        <button
                                            type="button"
                                            class="rate-delete-permanent-button"
                                            data-id="<?= $rateId ?>"
                                            data-category="<?= htmlspecialchars($category) ?>"
                                        >
                                            DELETE PERMANENTLY
                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="rate-edit-button"
                                            data-id="<?= $rateId ?>"
                                            data-rate-type="<?= htmlspecialchars($rateType) ?>"
                                            data-category="<?= htmlspecialchars($category) ?>"
                                            data-description="<?= htmlspecialchars($description) ?>"
                                            data-price="<?= htmlspecialchars((string) $price) ?>"
                                            data-unit="<?= htmlspecialchars($unit) ?>"
                                        >
                                            EDIT
                                        </button>

                                        <button
                                            type="button"
                                            class="rate-toggle-button <?= $isActive ? '' : 'activate' ?>"
                                            data-id="<?= $rateId ?>"
                                            data-active="<?= $isActive ? '1' : '0' ?>"
                                        >
                                            <?= $isActive ? 'DEACTIVATE' : 'ACTIVATE' ?>
                                        </button>

                                        <button
                                            type="button"
                                            class="rate-delete-button"
                                            data-id="<?= $rateId ?>"
                                            data-category="<?= htmlspecialchars($category) ?>"
                                        >
                                            DELETE
                                        </button>

                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <div
                id="filteredRatesEmpty"
                class="filtered-rates-empty"
                style="display: none;"
            >
                <div>⌕</div>
                <h3>No matching rates</h3>
                <p>Try changing your search or filters.</p>
            </div>

        <?php endif; ?>

    </div>

</div>



<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div class="rate-modal-overlay" id="rateModalOverlay">

    <div class="rate-modal">

        <div class="rate-modal-header">
            <h3 id="rateModalTitle">Add Buying Rate</h3>
            <button type="button" class="rate-modal-close" id="rateModalClose">×</button>
        </div>

        <div class="rate-modal-body">

            <div class="rate-modal-error" id="rateModalError"></div>

            <input type="hidden" id="rateFormId" value="">

            <div class="rate-field-row">

                <div class="rate-field">
                    <label>RATE TYPE</label>
                    <select id="rateFormType">
                        <option value="phone">Phone</option>
                        <option value="bagsakan">Board Code</option>
                        <option value="non_code_board">Non-Code Board</option>
                    </select>
                </div>

                <div class="rate-field">
                    <label>UNIT</label>
                    <select id="rateFormUnit">
                        <option value="piece">Piece</option>
                        <option value="kg">Kg</option>
                        <option value="gram">Gram</option>
                        <option value="set">Set</option>
                        <option value="lot">Lot</option>
                    </select>
                </div>

            </div>

            <div class="rate-field">
                <label>CATEGORY (e.g. A1, 512GB)</label>
                <input type="text" id="rateFormCategory" maxlength="100" placeholder="A1">
            </div>

            <div class="rate-field">
                <label>DESCRIPTION (optional)</label>
                <textarea id="rateFormDescription" maxlength="255" placeholder="VNZ TECH phone buying price - A1"></textarea>
            </div>

            <div class="rate-field">
                <label>PRICE (₱)</label>
                <input type="number" id="rateFormPrice" min="0" step="0.01" placeholder="0.00">
            </div>

        </div>

        <div class="rate-modal-footer">
            <button type="button" class="rate-modal-cancel" id="rateModalCancel">CANCEL</button>
            <button type="button" class="rate-modal-save" id="rateModalSave">SAVE RATE</button>
        </div>

    </div>

</div>


<div class="rate-toast" id="rateToast"></div>


<script>

(function () {

    const csrfToken =
        <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const searchInput = document.getElementById('rateSearch');
    const rateTypeFilterSelect = document.getElementById('rateTypeFilterSelect');
    const statusFilterSelect = document.getElementById('rateStatusFilterSelect');
    const clearButton = document.getElementById('clearRateFilters');
    const visibleCount = document.getElementById('rateVisibleCount');
    const filteredEmpty = document.getElementById('filteredRatesEmpty');

    const rows = Array.from(
        document.querySelectorAll('#ratesTableBody tr')
    );


    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    function applyFilters() {

        const search = (searchInput?.value || '').trim().toLowerCase();
        const selectedType = rateTypeFilterSelect?.value || '';
        const selectedStatus = statusFilterSelect?.value || '';

        let visibleRows = 0;

        rows.forEach(function (row) {

            const rowSearch = row.dataset.search || '';
            const rowType = row.dataset.rateType || '';
            const rowStatus = row.dataset.status || '';

            const matchesSearch = search === '' || rowSearch.includes(search);
            const matchesType = selectedType === '' || rowType === selectedType;
            const matchesStatus = selectedStatus === '' || rowStatus === selectedStatus;

            const show = matchesSearch && matchesType && matchesStatus;

            row.style.display = show ? '' : 'none';

            if (show) {
                visibleRows++;
            }

        });

        if (visibleCount) {
            visibleCount.textContent =
                'Showing ' + visibleRows.toLocaleString() + ' rate' + (visibleRows !== 1 ? 's' : '');
        }

        if (filteredEmpty) {
            filteredEmpty.style.display =
                rows.length > 0 && visibleRows === 0 ? 'block' : 'none';
        }

    }

    searchInput?.addEventListener('input', applyFilters);
    rateTypeFilterSelect?.addEventListener('change', applyFilters);
    statusFilterSelect?.addEventListener('change', applyFilters);

    clearButton?.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (rateTypeFilterSelect) rateTypeFilterSelect.value = '';
        if (statusFilterSelect) statusFilterSelect.value = '';
        applyFilters();
    });


    /*
    |--------------------------------------------------------------------------
    | Toast
    |--------------------------------------------------------------------------
    */

    const toastEl = document.getElementById('rateToast');
    let toastTimeout = null;

    function showToast(message, isError) {

        if (!toastEl) return;

        toastEl.textContent = message;
        toastEl.classList.toggle('error', !!isError);
        toastEl.classList.add('active');

        clearTimeout(toastTimeout);

        toastTimeout = setTimeout(function () {
            toastEl.classList.remove('active');
        }, 3000);

    }


    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    */

    const modalOverlay = document.getElementById('rateModalOverlay');
    const modalTitle = document.getElementById('rateModalTitle');
    const modalError = document.getElementById('rateModalError');
    const modalSaveButton = document.getElementById('rateModalSave');

    const formId = document.getElementById('rateFormId');
    const formType = document.getElementById('rateFormType');
    const formUnit = document.getElementById('rateFormUnit');
    const formCategory = document.getElementById('rateFormCategory');
    const formDescription = document.getElementById('rateFormDescription');
    const formPrice = document.getElementById('rateFormPrice');

    function openModal(mode, data) {

        modalError.classList.remove('active');
        modalError.textContent = '';

        if (mode === 'edit' && data) {

            modalTitle.textContent = 'Edit Buying Rate';
            formId.value = data.id;
            formType.value = data.rateType;
            formUnit.value = data.unit || 'piece';
            formCategory.value = data.category;
            formDescription.value = data.description || '';
            formPrice.value = data.price;

        } else {

            modalTitle.textContent = 'Add Buying Rate';
            formId.value = '';
            formType.value = 'phone';
            formUnit.value = 'piece';
            formCategory.value = '';
            formDescription.value = '';
            formPrice.value = '';

        }

        modalOverlay.classList.add('active');

    }

    function closeModal() {
        modalOverlay.classList.remove('active');
    }

    document.getElementById('openAddRateModal')?.addEventListener('click', function () {
        openModal('add', null);
    });

    document.querySelectorAll('.rates-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const targetView = this.dataset.view;
            if (typeof window.loadAdminPage === 'function') {
                window.loadAdminPage('buying_rates', { view: targetView });
            }
        });
    });

    document.getElementById('rateModalClose')?.addEventListener('click', closeModal);
    document.getElementById('rateModalCancel')?.addEventListener('click', closeModal);

    modalOverlay?.addEventListener('click', function (event) {
        if (event.target === modalOverlay) closeModal();
    });

    document.querySelectorAll('.rate-edit-button').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal('edit', {
                id: this.dataset.id,
                rateType: this.dataset.rateType,
                category: this.dataset.category,
                description: this.dataset.description,
                price: this.dataset.price,
                unit: this.dataset.unit
            });
        });
    });


    /*
    |--------------------------------------------------------------------------
    | Save (Create / Update)
    |--------------------------------------------------------------------------
    */

    modalSaveButton?.addEventListener('click', async function () {

        const id = formId.value;
        const rateType = formType.value;
        const unit = formUnit.value;
        const category = formCategory.value.trim();
        const description = formDescription.value.trim();
        const price = formPrice.value;

        if (category === '') {
            modalError.textContent = 'Category is required.';
            modalError.classList.add('active');
            return;
        }

        if (price === '' || isNaN(price) || Number(price) < 0) {
            modalError.textContent = 'Please enter a valid price.';
            modalError.classList.add('active');
            return;
        }

        const body = new URLSearchParams();
        body.set('action', id ? 'update' : 'create');
        body.set('csrf_token', csrfToken);
        if (id) body.set('id', id);
        body.set('rate_type', rateType);
        body.set('category', category);
        body.set('description', description);
        body.set('price', price);
        body.set('unit', unit);

        modalSaveButton.disabled = true;
        modalSaveButton.textContent = 'SAVING...';

        try {

            const response = await fetch(
                '/VNZ-TECH/backend/manage_buying_rate.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: body.toString()
                }
            );

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Unable to save rate.');
            }

            closeModal();
            showToast(data.message || 'Saved.', false);

            if (typeof window.loadAdminPage === 'function') {
                window.loadAdminPage('buying_rates', {});
            }

        } catch (error) {

            modalError.textContent = error.message;
            modalError.classList.add('active');

        } finally {

            modalSaveButton.disabled = false;
            modalSaveButton.textContent = 'SAVE RATE';

        }

    });


    /*
    |--------------------------------------------------------------------------
    | Toggle Active
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.rate-toggle-button').forEach(function (button) {

        button.addEventListener('click', async function () {

            const id = this.dataset.id;
            const originalText = this.textContent;

            this.disabled = true;
            this.textContent = '...';

            const body = new URLSearchParams();
            body.set('action', 'toggle');
            body.set('csrf_token', csrfToken);
            body.set('id', id);

            try {

                const response = await fetch(
                    '/VNZ-TECH/backend/manage_buying_rate.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        credentials: 'same-origin',
                        body: body.toString()
                    }
                );

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Unable to update rate.');
                }

                showToast(data.message || 'Updated.', false);

                if (typeof window.loadAdminPage === 'function') {
                    window.loadAdminPage('buying_rates', {});
                }

            } catch (error) {

                showToast(error.message, true);
                this.disabled = false;
                this.textContent = originalText;

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Delete (Archive)
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.rate-delete-button').forEach(function (button) {

        button.addEventListener('click', async function () {

            const id = this.dataset.id;
            const category = this.dataset.category || 'this rate';

            if (!confirm('Move "' + category + '" to the archive? You can restore it later, or permanently delete it from the Archived tab.')) {
                return;
            }

            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = '...';

            const body = new URLSearchParams();
            body.set('action', 'archive');
            body.set('csrf_token', csrfToken);
            body.set('id', id);

            try {

                const response = await fetch(
                    '/VNZ-TECH/backend/manage_buying_rate.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        credentials: 'same-origin',
                        body: body.toString()
                    }
                );

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Unable to archive rate.');
                }

                showToast(data.message || 'Moved to archive.', false);

                if (typeof window.loadAdminPage === 'function') {
                    window.loadAdminPage('buying_rates', {});
                }

            } catch (error) {

                showToast(error.message, true);
                this.disabled = false;
                this.textContent = originalText;

            }

        });

    });


    /*
    |--------------------------------------------------------------------------
    | Restore
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.rate-restore-button').forEach(function (button) {

        button.addEventListener('click', async function () {

            const id = this.dataset.id;
            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = '...';

            const body = new URLSearchParams();
            body.set('action', 'restore');
            body.set('csrf_token', csrfToken);
            body.set('id', id);

            try {

                const response = await fetch(
                    '/VNZ-TECH/backend/manage_buying_rate.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        credentials: 'same-origin',
                        body: body.toString()
                    }
                );

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Unable to restore rate.');
                }

                showToast(data.message || 'Rate restored.', false);

                if (typeof window.loadAdminPage === 'function') {
                    window.loadAdminPage('buying_rates', { view: 'archived' });
                }

            } catch (error) {

                showToast(error.message, true);
                this.disabled = false;
                this.textContent = originalText;

            }

        });

    });


    /*
    |--------------------------------------------------------------------------
    | Delete Permanently
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.rate-delete-permanent-button').forEach(function (button) {

        button.addEventListener('click', async function () {

            const id = this.dataset.id;
            const category = this.dataset.category || 'this rate';

            if (!confirm('Permanently delete "' + category + '"? This cannot be undone.')) {
                return;
            }

            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = '...';

            const body = new URLSearchParams();
            body.set('action', 'delete_permanent');
            body.set('csrf_token', csrfToken);
            body.set('id', id);

            try {

                const response = await fetch(
                    '/VNZ-TECH/backend/manage_buying_rate.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        credentials: 'same-origin',
                        body: body.toString()
                    }
                );

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Unable to delete rate.');
                }

                showToast(data.message || 'Rate permanently deleted.', false);

                if (typeof window.loadAdminPage === 'function') {
                    window.loadAdminPage('buying_rates', { view: 'archived' });
                }

            } catch (error) {

                showToast(error.message, true);
                this.disabled = false;
                this.textContent = originalText;

            }

        });

    });

})();

</script>
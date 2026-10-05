<?php
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';

requireAdminLogin();

if (!hasPermission('manage_trades')) {
    http_response_code(403);
    echo '<div class="trade-page"><div class="trade-access-denied"><div class="trade-access-denied-icon">!</div><div><h2>Access Denied</h2><p>You do not have permission to manage trade items.</p></div></div></div>';
    exit;
}

$csrfToken = generateCsrfToken();

$stmt = $pdo->query("
    SELECT
        ti.*,
        created_admin.first_name AS created_by_first_name,
        created_admin.last_name AS created_by_last_name,
        updated_admin.first_name AS updated_by_first_name,
        updated_admin.last_name AS updated_by_last_name
    FROM trade_items ti
    LEFT JOIN admins created_admin
        ON created_admin.id = ti.created_by_admin_id
    LEFT JOIN admins updated_admin
        ON updated_admin.id = ti.updated_by_admin_id
    ORDER BY ti.sort_order ASC, ti.id DESC
");

$tradeItems = $stmt->fetchAll();

$summaryStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_items,
        SUM(is_active = 1) AS active_items,
        SUM(is_featured = 1) AS featured_items
    FROM trade_items
");

$summary = $summaryStmt->fetch();

$requirementMap = [];

foreach ($tradeItems as $tradeItem) {
    $reqStmt = $pdo->prepare("
        SELECT *
        FROM trade_requirements
        WHERE trade_item_id = ?
        ORDER BY sort_order ASC, id ASC
    ");

    $reqStmt->execute([$tradeItem['id']]);
    $requirementMap[(int) $tradeItem['id']] = $reqStmt->fetchAll();
}

function tradeRequirementTypeLabel(string $type): string
{
    return $type === 'scrap_board' ? 'Scrap Board' : 'Phone';
}

?>

<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_trade_items.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_trade_items.css') ?>">

<div class="trade-page trade-items-page">

    <div class="trade-header">

        <div class="trade-heading">
            <div class="trade-kicker">VNZ TECH MANAGEMENT</div>
            <h1>Trade Items</h1>
            <p>Manage the exchange items available to sellers and the public trade reward system.</p>
        </div>

        <button type="button" class="trade-primary-btn" id="newTradeItemBtn">
            + Add Trade Item
        </button>

    </div>

    <div class="trade-summary-grid">
        <div class="trade-summary-card">
            <span>Total Items</span>
            <strong><?= (int) ($summary['total_items'] ?? 0) ?></strong>
        </div>
        <div class="trade-summary-card">
            <span>Active</span>
            <strong><?= (int) ($summary['active_items'] ?? 0) ?></strong>
        </div>
        <div class="trade-summary-card">
            <span>Featured</span>
            <strong><?= (int) ($summary['featured_items'] ?? 0) ?></strong>
        </div>
    </div>

    <form id="tradeItemForm" class="trade-form" enctype="multipart/form-data">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" id="tradeActionInput" value="create_item">
        <input type="hidden" name="id" id="tradeItemIdInput" value="">
        <input type="hidden" name="requirements" id="tradeRequirementsJson" value="[]">

        <div class="trade-form-header">
            <div>
                <span class="trade-form-kicker">TRADE ITEM</span>
                <h2 id="tradeFormTitle">Create Trade Item</h2>
            </div>
            <button type="button" class="trade-secondary-btn" id="resetTradeFormBtn">Reset</button>
        </div>

        <div class="trade-form-grid">

            <div class="trade-field trade-field-full">
                <label for="trade_name">Name</label>
                <input type="text" id="trade_name" name="name" maxlength="120" placeholder="Second-hand Refrigerator" required>
            </div>

            <div class="trade-field">
                <label for="trade_condition">Condition</label>
                <input type="text" id="trade_condition" name="condition" maxlength="120" placeholder="Second-hand" required>
            </div>

            <div class="trade-field">
                <label for="trade_sort_order">Sort Order</label>
                <input type="number" id="trade_sort_order" name="sort_order" min="0" step="1" value="0">
            </div>

            <div class="trade-field trade-field-full">
                <label for="trade_description">Description</label>
                <textarea id="trade_description" name="description" rows="4" maxlength="2000" placeholder="Describe the trade item and what the user receives."></textarea>
            </div>

            <div class="trade-field trade-field-full">
                <label for="trade_image">Image</label>
                <input type="file" id="trade_image" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="trade-field-note">JPG, PNG, or WEBP up to 5MB.</small>
            </div>

            <div class="trade-toggle-row trade-field-full">
                <label class="trade-toggle">
                    <input type="checkbox" name="is_featured" value="1">
                    <span>Featured</span>
                </label>

                <label class="trade-toggle">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active</span>
                </label>
            </div>

        </div>

        <div class="trade-requirements-panel">

            <div class="trade-requirements-header">
                <div>
                    <span class="trade-form-kicker">REQUIREMENTS</span>
                    <h3>Required Exchange Items</h3>
                </div>
                <button type="button" class="trade-secondary-btn" id="addRequirementBtn">+ Add Requirement</button>
            </div>

            <div id="tradeRequirementsList" class="trade-requirements-list"></div>

        </div>

        <div class="trade-form-actions">
            <button type="submit" class="trade-primary-btn" id="saveTradeItemBtn">Save Trade Item</button>
            <button type="button" class="trade-secondary-btn" id="cancelTradeEditBtn">Cancel</button>
        </div>

    </form>

    <div class="trade-list-wrapper">
        <div class="trade-list-header">
            <div>
                <span class="trade-form-kicker">ALL ITEMS</span>
                <h3>Trade Inventory</h3>
            </div>
        </div>

        <?php if (empty($tradeItems)): ?>
            <div class="trade-empty-state">
                No trade items yet. Add your first exchange item above.
            </div>
        <?php else: ?>
            <div class="trade-card-list">
                <?php foreach ($tradeItems as $tradeItem): ?>
                    <?php
                    $itemRequirements = $requirementMap[(int) $tradeItem['id']] ?? [];
                    $imageUrl = !empty($tradeItem['image_path']) ? '/VNZ-TECH/' . $tradeItem['image_path'] : '/VNZ-TECH/assets/images/vnz.png';
                    ?>
                    <article class="trade-item-card" data-trade-id="<?= (int) $tradeItem['id'] ?>">
                        <div class="trade-item-image-wrap">
                            <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.src='/VNZ-TECH/assets/images/vnz.png';">
                        </div>

                        <div class="trade-item-content">
                            <div class="trade-item-topline">
                                <div>
                                    <h4><?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <p><?= htmlspecialchars($tradeItem['condition'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="trade-status-badge <?= $tradeItem['is_active'] ? 'active' : 'inactive' ?>"><?= $tradeItem['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>

                            <div class="trade-item-meta-row">
                                <span class="trade-feature-pill <?= $tradeItem['is_featured'] ? 'featured' : '' ?>"><?= $tradeItem['is_featured'] ? 'Featured' : 'Standard' ?></span>
                                <span>Sort: <?= (int) $tradeItem['sort_order'] ?></span>
                            </div>

                            <?php if (!empty($tradeItem['description'])): ?>
                                <p class="trade-item-description"><?= htmlspecialchars($tradeItem['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>

                            <?php if (!empty($itemRequirements)): ?>
                                <div class="trade-item-requirements">
                                    <?php foreach ($itemRequirements as $requirement): ?>
                                        <div class="trade-item-requirement-row">
                                            <strong><?= (int) $requirement['quantity'] ?> × <?= tradeRequirementTypeLabel((string) $requirement['requirement_type']) ?></strong>
                                            <span><?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="trade-item-requirements empty">No requirements configured.</div>
                            <?php endif; ?>

                            <div class="trade-card-actions">
                                <button type="button" class="trade-card-btn trade-edit-btn" data-trade-action="edit" data-trade-id="<?= (int) $tradeItem['id'] ?>">Edit</button>
                                <button type="button" class="trade-card-btn trade-toggle-btn" data-trade-action="toggle_active" data-trade-id="<?= (int) $tradeItem['id'] ?>"><?= $tradeItem['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                <button type="button" class="trade-card-btn trade-toggle-btn" data-trade-action="toggle_featured" data-trade-id="<?= (int) $tradeItem['id'] ?>"><?= $tradeItem['is_featured'] ? 'Unfeature' : 'Feature' ?></button>
                                <button type="button" class="trade-card-btn trade-delete-btn" data-trade-action="delete" data-trade-id="<?= (int) $tradeItem['id'] ?>">Delete</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    const form = document.getElementById('tradeItemForm');
    const formTitle = document.getElementById('tradeFormTitle');
    const actionInput = document.getElementById('tradeActionInput');
    const idInput = document.getElementById('tradeItemIdInput');
    const requirementsList = document.getElementById('tradeRequirementsList');
    const requirementJson = document.getElementById('tradeRequirementsJson');
    const addRequirementBtn = document.getElementById('addRequirementBtn');
    const resetBtn = document.getElementById('resetTradeFormBtn');
    const cancelEditBtn = document.getElementById('cancelTradeEditBtn');
    const newTradeItemBtn = document.getElementById('newTradeItemBtn');
    const allowedTypes = [
        { value: 'phone', label: 'Phone' },
        { value: 'scrap_board', label: 'Scrap Board' }
    ];

    function requirementTypeLabel(value) {
        const found = allowedTypes.find((type) => type.value === value);
        return found ? found.label : 'Phone';
    }

    function buildRequirementRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'trade-requirement-row';
        row.dataset.id = data.id || '';

        row.innerHTML = `
            <div class="trade-requirement-grid">
                <div class="trade-field">
                    <label>Type</label>
                    <select name="requirement_type" class="trade-requirement-type">
                        ${allowedTypes.map((type) => `
                            <option value="${type.value}" ${data.requirement_type === type.value ? 'selected' : ''}>${type.label}</option>
                        `).join('')}
                    </select>
                </div>

                <div class="trade-field">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" step="1" value="${data.quantity || 1}" required>
                </div>

                <div class="trade-field">
                    <label>Storage / Classification</label>
                    <input type="text" name="storage" maxlength="100" value="${(data.storage || '').replace(/"/g, '&quot;')}" placeholder="128GB / A3" required>
                </div>

                <div class="trade-field trade-field-large">
                    <label>Description</label>
                    <input type="text" name="description" maxlength="500" value="${(data.description || '').replace(/"/g, '&quot;')}" placeholder="Accepted condition or notes">
                </div>

                <div class="trade-field requirement-sort-field">
                    <label>Order</label>
                    <input type="number" name="sort_order" min="0" step="1" value="${data.sort_order || 0}">
                </div>
            </div>

            <div class="trade-requirement-actions">
                <button type="button" class="trade-mini-btn" data-requirement-move="up">↑</button>
                <button type="button" class="trade-mini-btn" data-requirement-move="down">↓</button>
                <button type="button" class="trade-mini-btn trade-remove-requirement" data-requirement-remove="true">Remove</button>
            </div>
        `;

        row.querySelector('[data-requirement-remove]').addEventListener('click', function () {
            row.remove();
            syncRequirementsJson();
        });

        row.querySelector('[data-requirement-move="up"]').addEventListener('click', function () {
            const prev = row.previousElementSibling;
            if (prev) {
                row.parentNode.insertBefore(row, prev);
                syncRequirementsJson();
            }
        });

        row.querySelector('[data-requirement-move="down"]').addEventListener('click', function () {
            const next = row.nextElementSibling;
            if (next) {
                next.parentNode.insertBefore(next, row);
                syncRequirementsJson();
            }
        });

        return row;
    }

    function syncRequirementsJson() {
        const rows = requirementsList.querySelectorAll('.trade-requirement-row');
        const requirements = [];

        rows.forEach(function (row, index) {
            const type = row.querySelector('.trade-requirement-type')?.value || 'phone';
            const quantity = Number(row.querySelector('input[name="quantity"]')?.value || 1);
            const storage = row.querySelector('input[name="storage"]')?.value || '';
            const description = row.querySelector('input[name="description"]')?.value || '';
            const sortOrder = Number(row.querySelector('input[name="sort_order"]')?.value || index + 1);

            const item = {
                id: row.dataset.id || null,
                requirement_type: type,
                quantity: quantity > 0 ? quantity : 1,
                storage: storage,
                description: description,
                sort_order: sortOrder
            };

            requirements.push(item);
        });

        requirementJson.value = JSON.stringify(requirements);
    }

    function addRequirementRow(data = {}) {
        const row = buildRequirementRow(data);
        requirementsList.appendChild(row);
        syncRequirementsJson();
    }

    function resetTradeForm() {
        form.reset();
        actionInput.value = 'create_item';
        idInput.value = '';
        formTitle.textContent = 'Create Trade Item';
        requirementsList.innerHTML = '';
        addRequirementRow({ requirement_type: 'phone', quantity: 1, storage: '128GB', description: '', sort_order: 1 });
        document.querySelector('input[name="is_active"]').checked = true;
    }

    function populateTradeForm(item) {
        formTitle.textContent = 'Edit Trade Item';
        actionInput.value = 'update_item';
        idInput.value = String(item.id || '');

        const nameField = document.getElementById('trade_name');
        const conditionField = document.getElementById('trade_condition');
        const descriptionField = document.getElementById('trade_description');
        const sortField = document.getElementById('trade_sort_order');
        const featuredField = document.querySelector('input[name="is_featured"]');
        const activeField = document.querySelector('input[name="is_active"]');

        nameField.value = item.name || '';
        conditionField.value = item.condition || '';
        descriptionField.value = item.description || '';
        sortField.value = item.sort_order || 0;
        featuredField.checked = !!Number(item.is_featured);
        activeField.checked = !!Number(item.is_active);

        requirementsList.innerHTML = '';

        const requirementItems = item.requirements || [];
        if (!requirementItems.length) {
            addRequirementRow({ requirement_type: 'phone', quantity: 1, storage: '128GB', description: '', sort_order: 1 });
            return;
        }

        requirementItems.forEach(function (requirement, index) {
            addRequirementRow({
                id: requirement.id || null,
                requirement_type: requirement.requirement_type || 'phone',
                quantity: requirement.quantity || 1,
                storage: requirement.storage || '',
                description: requirement.description || '',
                sort_order: requirement.sort_order || index + 1
            });
        });
    }

    addRequirementBtn.addEventListener('click', function () {
        addRequirementRow({
            requirement_type: 'phone',
            quantity: 1,
            storage: '128GB',
            description: '',
            sort_order: requirementsList.children.length + 1
        });
    });

    resetBtn.addEventListener('click', resetTradeForm);
    cancelEditBtn.addEventListener('click', resetTradeForm);
    newTradeItemBtn.addEventListener('click', function () {
        resetTradeForm();
        document.getElementById('tradeItemForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        const formData = new FormData(form);
        syncRequirementsJson();
        formData.set('requirements', requirementJson.value);

        try {
            const response = await fetch('/VNZ-TECH/backend/manage_trade.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to save trade item.');
            }

            window.location.reload();
        } catch (error) {
            window.alert(error.message || 'Unable to save trade item.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Save Trade Item';
        }
    });

    document.querySelectorAll('[data-trade-action]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const action = button.dataset.tradeAction;
            const itemId = Number(button.dataset.tradeId);

            if (action === 'edit') {
                const card = button.closest('.trade-item-card');
                const itemFromCard = {
                    id: itemId,
                    name: card.querySelector('h4')?.textContent || '',
                    condition: card.querySelector('.trade-item-topline p')?.textContent || '',
                    description: card.querySelector('.trade-item-description')?.textContent || '',
                    is_featured: card.querySelector('.trade-feature-pill')?.classList.contains('featured') ? 1 : 0,
                    is_active: card.querySelector('.trade-status-badge')?.classList.contains('active') ? 1 : 0,
                    sort_order: Number((card.querySelector('.trade-item-meta-row span:last-child')?.textContent || '').replace(/[^0-9-]/g, '')) || 0,
                    requirements: []
                };

                const requirementRows = Array.from(card.querySelectorAll('.trade-item-requirement-row'));
                itemFromCard.requirements = requirementRows.map(function (row) {
                    const strongText = row.querySelector('strong')?.textContent || '1 × Phone';
                    const quantityMatch = strongText.match(/(\d+)/);
                    const quantity = quantityMatch ? Number(quantityMatch[1]) : 1;
                    const requirementType = strongText.includes('Scrap Board') ? 'scrap_board' : 'phone';
                    const storageText = row.querySelector('span')?.textContent || '128GB';
                    return {
                        id: null,
                        requirement_type: requirementType,
                        quantity: quantity,
                        storage: storageText,
                        description: '',
                        sort_order: 1
                    };
                });

                populateTradeForm(itemFromCard);
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            if (action === 'delete') {
                const confirmed = window.confirm('Delete this trade item and all its requirements?');
                if (!confirmed) {
                    return;
                }
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', String(itemId));
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            try {
                const response = await fetch('/VNZ-TECH/backend/manage_trade.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to update trade item.');
                }

                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Unable to complete that action.');
            }
        });
    });

    resetTradeForm();
})();
</script>

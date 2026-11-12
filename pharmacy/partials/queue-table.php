<?php
/**
 * Pharmacy queue table partial — used on pharmacist Dashboard.
 *
 * @var list<array<string, mixed>> $queue
 * @var string $statusFilter
 * @var string $searchQuery
 * @var string $sortBy
 */
$searchQuery = $searchQuery ?? '';
$sortBy = $sortBy ?? 'newest';
$queueCount = count($queue);

$statWaiting = 0;
$statDispensing = 0;
foreach ($queue as $qRow) {
    $tone = $qRow['status_tone'] ?? '';
    if ($tone === 'waiting') {
        $statWaiting++;
    } elseif ($tone === 'dispensing') {
        $statDispensing++;
    }
}

$patientInitials = static function (string $formalName): string {
    $parts = preg_split('/[\s,]+/', trim($formalName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return '?';
};
?>
<div class="ph-page ph-dashboard">
    <div class="ph-shell">
    <header class="ph-header">
        <div class="ph-header-main">
            <span class="ph-header-icon" aria-hidden="true"><i class="bi bi-capsule"></i></span>
            <div>
                <h1 class="ph-title">Pharmacy Queue</h1>
                <p class="ph-subtitle">Patients waiting for the pharmacist</p>
            </div>
        </div>
        <div class="ph-header-stats">
            <span class="ph-stat-pill ph-stat-pill--total">
                <strong><?= (int) $queueCount ?></strong>
                <?= $queueCount === 1 ? 'patient' : 'patients' ?>
            </span>
            <?php if ($queueCount > 0): ?>
            <span class="ph-stat-pill ph-stat-pill--waiting" title="Waiting for pharmacy">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= (int) $statWaiting ?>
            </span>
            <?php if ($statDispensing > 0): ?>
            <span class="ph-stat-pill ph-stat-pill--dispensing" title="Dispensing">
                <i class="bi bi-box-seam" aria-hidden="true"></i> <?= (int) $statDispensing ?>
            </span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <form class="ph-toolbar" method="get" action="<?= BASE_URL ?>/dashboard.php" id="phQueueFilters">
        <div class="ph-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                name="q"
                id="phSearch"
                value="<?= htmlspecialchars($searchQuery) ?>"
                placeholder="Search patient name, patient ID or queue number…"
                aria-label="Search pharmacy queue"
                autocomplete="off"
            >
            <button type="button" class="ph-search-clear<?= $searchQuery === '' ? ' d-none' : '' ?>" id="phSearchClear" aria-label="Clear search">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <label class="ph-filter">
            <span>Status</span>
            <select name="status" id="phStatusFilter" aria-label="Filter by prescription status">
                <option value="">Waiting for pharmacist</option>
                <option value="<?= htmlspecialchars(WORKFLOW_WAITING_FOR_PHARMACY) ?>" <?= $statusFilter === WORKFLOW_WAITING_FOR_PHARMACY ? 'selected' : '' ?>>Waiting for Pharmacy</option>
                <option value="<?= htmlspecialchars(WORKFLOW_DISPENSING) ?>" <?= $statusFilter === WORKFLOW_DISPENSING ? 'selected' : '' ?>>Dispensing</option>
            </select>
        </label>

        <label class="ph-filter">
            <span>Sort by</span>
            <select name="sort" id="phSortFilter" aria-label="Sort pharmacy queue">
                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            </select>
        </label>

        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary ph-reset">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset
        </a>
    </form>

    <?php if (!$queue): ?>
    <div class="ph-empty">
        <i class="bi bi-capsule"></i>
        <h3>No patients in the pharmacy queue</h3>
        <p>
            <?php if ($searchQuery !== '' || $statusFilter !== ''): ?>
                No matches for the current filters. Try clearing search or status.
            <?php else: ?>
                Consultations appear here after the doctor clicks <strong>Send to Pharmacy</strong>.
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <div class="ph-table-wrap">
        <table class="ph-table ph-queue-table">
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Patient Information</th>
                    <th>Consultation Date &amp; Time</th>
                    <th>Prescribing Doctor</th>
                    <th>Status</th>
                    <th>Medications</th>
                    <th class="ph-actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue as $row): ?>
                <tr>
                    <td>
                        <span class="ph-qbadge ph-qbadge--<?= htmlspecialchars($row['status_tone']) ?>">
                            <?= htmlspecialchars($row['pharmacy_queue_no']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="ph-patient-cell">
                            <span class="ph-patient-avatar" aria-hidden="true"><?= htmlspecialchars($patientInitials($row['formal_name'])) ?></span>
                            <div>
                                <div class="ph-name"><?= htmlspecialchars(strtoupper($row['formal_name'])) ?></div>
                                <div class="ph-sub"><?= htmlspecialchars($row['patient_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="ph-date"><?= htmlspecialchars($row['consult_date']) ?></div>
                        <?php if ($row['consult_time'] !== ''): ?>
                        <div class="ph-sub"><?= htmlspecialchars($row['consult_time']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['doctor']) ?></td>
                    <td>
                        <span class="ph-status ph-status--<?= htmlspecialchars($row['status_tone']) ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                        <?php if ($row['status_since'] !== ''): ?>
                        <div class="ph-since"><?= htmlspecialchars($row['status_since']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="ph-meds">
                            <i class="bi bi-capsule-pill" aria-hidden="true"></i>
                            <?= htmlspecialchars($row['medication_label']) ?>
                        </span>
                    </td>
                    <td class="ph-actions ph-actions-col">
                        <a class="btn btn-primary ph-action-btn" href="<?= htmlspecialchars($row['dispense_url']) ?>" title="<?= htmlspecialchars($row['action_label']) ?>">
                            <i class="bi <?= htmlspecialchars($row['action_icon']) ?>" aria-hidden="true"></i>
                            <span class="ph-action-label"><?= htmlspecialchars($row['action_label']) ?></span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('phQueueFilters');
    if (!form) return;
    const status = document.getElementById('phStatusFilter');
    const sort = document.getElementById('phSortFilter');
    const search = document.getElementById('phSearch');
    const clearBtn = document.getElementById('phSearchClear');
    let timer = null;

    status?.addEventListener('change', () => form.submit());
    sort?.addEventListener('change', () => form.submit());
    search?.addEventListener('input', () => {
        clearBtn?.classList.toggle('d-none', !search.value.trim());
        clearTimeout(timer);
        timer = setTimeout(() => form.submit(), 400);
    });
    clearBtn?.addEventListener('click', () => {
        if (!search) return;
        search.value = '';
        clearBtn.classList.add('d-none');
        search.focus();
        form.submit();
    });
})();
</script>

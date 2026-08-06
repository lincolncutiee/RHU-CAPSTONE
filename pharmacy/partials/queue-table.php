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
?>
<div class="ph-page ph-dashboard">
    <header class="ph-header">
        <div>
            <h1 class="ph-title">Pharmacy Queue</h1>
            <p class="ph-subtitle">Patients sent by the doctor for medicine dispensing</p>
        </div>
        <div class="ph-count">
            <strong><?= (int) $queueCount ?></strong>
            <?= $queueCount === 1 ? 'patient' : 'patients' ?>
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
        </div>

        <label class="ph-filter">
            <span>Status</span>
            <select name="status" id="phStatusFilter" aria-label="Filter by prescription status">
                <option value="">All Statuses</option>
                <option value="<?= htmlspecialchars(WORKFLOW_WAITING_FOR_PHARMACY) ?>" <?= $statusFilter === WORKFLOW_WAITING_FOR_PHARMACY ? 'selected' : '' ?>>Waiting for Pharmacy</option>
                <option value="<?= htmlspecialchars(WORKFLOW_DISPENSING) ?>" <?= $statusFilter === WORKFLOW_DISPENSING ? 'selected' : '' ?>>Dispensing</option>
                <option value="<?= htmlspecialchars(WORKFLOW_DISPENSED) ?>" <?= $statusFilter === WORKFLOW_DISPENSED ? 'selected' : '' ?>>Dispensed</option>
            </select>
        </label>

        <label class="ph-filter">
            <span>Sort by</span>
            <select name="sort" id="phSortFilter" aria-label="Sort pharmacy queue">
                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>By Status</option>
                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Patient Name</option>
            </select>
        </label>

        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-primary ph-reset">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
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
                    <th class="text-end">Actions</th>
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
                        <div class="ph-name"><?= htmlspecialchars(strtoupper($row['formal_name'])) ?></div>
                        <div class="ph-sub"><?= htmlspecialchars($row['patient_id']) ?></div>
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
                        <span class="ph-meds"><?= htmlspecialchars($row['medication_label']) ?></span>
                    </td>
                    <td class="ph-actions text-end">
                        <a class="btn btn-primary ph-action-btn" href="<?= htmlspecialchars($row['dispense_url']) ?>">
                            <i class="bi <?= htmlspecialchars($row['action_icon']) ?>"></i>
                            <?= htmlspecialchars($row['action_label']) ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(() => {
    const form = document.getElementById('phQueueFilters');
    if (!form) return;
    const status = document.getElementById('phStatusFilter');
    const sort = document.getElementById('phSortFilter');
    const search = document.getElementById('phSearch');
    let timer = null;

    status?.addEventListener('change', () => form.submit());
    sort?.addEventListener('change', () => form.submit());
    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.submit(), 400);
    });
})();
</script>

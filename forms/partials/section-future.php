<?php
/** Reserved sections for future IT and Receptionist modules */
$itRo = Permissions::isReadonly($role, 'it');
$recRo = Permissions::isReadonly($role, 'receptionist');
?>
<div class="form-section section-it section-future <?= Permissions::canEdit($role, 'it') ? 'section-editable' : 'section-readonly' ?>" data-section="it">
    <div class="section-header">
        <h3><i class="bi bi-pc-display"></i> IT Department — Reserved Section</h3>
        <span class="section-badge future">Future Module</span>
    </div>
    <div class="section-body">
        <div class="future-placeholder">
            <i class="bi bi-tools"></i>
            <p>Official IT Department forms have not yet been collected. This modular section is reserved for future IT fields and will be editable by the IT Department once forms are obtained.</p>
        </div>
    </div>
</div>

<div class="form-section section-receptionist section-future <?= Permissions::canEdit($role, 'receptionist') ? 'section-editable' : 'section-readonly' ?>" data-section="receptionist">
    <div class="section-header">
        <h3><i class="bi bi-door-open"></i> Receptionist — Reserved Section</h3>
        <span class="section-badge future">Future Module</span>
    </div>
    <div class="section-body">
        <div class="future-placeholder">
            <i class="bi bi-tools"></i>
            <p>Official Receptionist forms have not yet been collected. This modular section is reserved for future Receptionist fields and will be editable by the Receptionist once forms are obtained.</p>
        </div>
    </div>
</div>

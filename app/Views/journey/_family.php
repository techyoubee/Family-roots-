<?php
/**
 * Shared journey header: family heading, family switcher and section tabs.
 *
 * @var array|null $family
 * @var array      $memberships
 * @var array      $stepUrls
 * @var string     $currentStep
 */
$steps = [
    'intro'    => ['label' => 'Overview', 'path' => '/journey'],
    'animate'  => ['label' => 'Cinematic journey', 'path' => '/journey/animate'],
    'tree'     => ['label' => 'Tree', 'path' => '/journey/tree'],
    'timeline' => ['label' => 'Timeline', 'path' => '/journey/timeline'],
];
?>
<header class="journey-head">
    <div class="journey-head__copy">
        <p class="eyebrow">My Ancestral Journey</p>
        <h1><?= e($family ? $family['name'] : 'Ancestral Journey') ?></h1>
        <p class="text-muted journey-head__lead">
            A guided view of the ancestry your family has recorded - nothing is assumed or invented.
            Every person and connection carries the status the family gave it.
        </p>
    </div>

    <?php if ($family !== null && count($memberships) > 1): ?>
        <div class="journey-family-switch">
            <span class="journey-family-switch__label">Family</span>
            <ul class="journey-family-switch__list">
                <?php foreach ($memberships as $membership): ?>
                    <?php
                    $isCurrent = (int) $membership['id'] === (int) $family['id'];
                    $target = $steps[$currentStep]['path'] ?? '/journey';
                    ?>
                    <li>
                        <a href="<?= e(query_url($target, ['family' => $membership['public_id']])) ?>"
                           class="<?= $isCurrent ? 'is-current' : '' ?>"
                           <?= $isCurrent ? 'aria-current="true"' : '' ?>>
                            <?= e($membership['name']) ?>
                            <span class="journey-family-switch__role"><?= e(ucfirst($membership['role'])) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</header>

<?php if ($family !== null): ?>
    <nav class="journey-tabs" aria-label="Journey sections">
        <?php foreach ($steps as $key => $step): ?>
            <?php
            if ($key === 'animate' && !empty($emptyReason)) {
                continue;
            }
            $active = $key === $currentStep;
            ?>
            <a href="<?= e(query_url($step['path'], ['family' => $family['public_id']])) ?>"
               class="journey-tabs__link <?= $active ? 'is-active' : '' ?>"
               <?= $active ? 'aria-current="page"' : '' ?>>
                <?= e($step['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

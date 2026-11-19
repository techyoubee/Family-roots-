<?php
/**
 * The signed-in user's membership applications.
 *
 * @var string|null $title
 * @var array       $requests
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Membership</p>
                <h1>Your applications</h1>
                <p class="text-muted">
                    A membership application links you to a family through the ancestors you can name.
                    Every decision is reviewed by a person - nothing here is granted from a surname alone.
                </p>
            </div>
            <div>
                <?php if (route_exists('applications.create')): ?>
                    <a class="btn btn--primary" href="<?= e(route('applications.create')) ?>">Start an application</a>
                <?php endif; ?>
                <?php if (route_exists('families.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('families.index')) ?>">Browse families</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <h3>You have not applied to any family yet</h3>
                <p>
                    If you believe you belong to a family listed here, choose it and describe the parents,
                    grandparents or older relatives you can document.
                </p>
                <?php if (route_exists('applications.create')): ?>
                    <a class="btn btn--primary" href="<?= e(route('applications.create')) ?>">Start an application</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Family</th>
                                <th scope="col">Reference</th>
                                <th scope="col">Status</th>
                                <th scope="col">Score</th>
                                <th scope="col">Updated</th>
                                <th scope="col"><span class="visually-hidden">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['family_name']) ?></strong>
                                        <div class="text-small text-muted"><?= e($row['family_public_id']) ?></div>
                                    </td>
                                    <td class="text-small"><?= e($row['public_id']) ?></td>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-small">
                                        <?php if ($row['match_score'] !== null): ?>
                                            <?= e(number_format((float) $row['match_score'], 1)) ?>%
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small"><?= e(format_date($row['updated_at'] ?? $row['created_at'])) ?></td>
                                    <td class="text-small"><a href="<?= e(route('applications.show', ['application' => $row['public_id']])) ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

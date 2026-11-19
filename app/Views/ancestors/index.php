<?php
/**
 * Ancestor records for one family: search, filter and browse.
 *
 * @var string|null $title
 * @var array      $family
 * @var array      $ancestors
 * @var array      $filters
 * @var int        $activeCount
 * @var int        $total
 * @var int        $page
 * @var int        $perPage
 * @var array      $branches
 * @var array      $genCounts
 * @var array      $statusCounts
 * @var bool       $canWrite
 * @var bool       $canAdmin
 */
$baseUrl = '/families/' . rawurlencode($family['public_id']) . '/ancestors';
$queryBase = [];
if ($filters['q'] !== '') {
    $queryBase['q'] = $filters['q'];
}
if ($filters['generation'] !== '') {
    $queryBase['generation'] = $filters['generation'];
}
if ($filters['branch'] !== '') {
    $queryBase['branch'] = $filters['branch'];
}
if ($filters['status'] !== '') {
    $queryBase['status'] = $filters['status'];
}
$lastPage = max(1, (int) ceil($total / $perPage));
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Ancestor records &middot; <?= e($family['public_id']) ?></p>
                <h1><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    Genealogy people, not login accounts. Records start as <strong>proposed</strong> - they become
                    confirmed only through review. Nothing here is merged automatically.
                </p>
            </div>
            <div>
                <?php if ($canWrite && route_exists('ancestors.create')): ?>
                    <a class="btn btn--primary" href="<?= e(route('ancestors.create', ['family' => $family['public_id']])) ?>">Add an ancestor</a>
                <?php endif; ?>
                <?php if ($canAdmin && route_exists('relationships.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('relationships.index', ['family' => $family['public_id']])) ?>">Relationships</a>
                <?php endif; ?>
                <?php if (route_exists('tree.show')): ?>
                    <a class="btn btn--outline" href="<?= e(route('tree.show', ['family' => $family['public_id']])) ?>">Family tree</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <form class="form" method="get" action="<?= e(url($baseUrl)) ?>">
                <div class="form-row form-row--split-3">
                    <div>
                        <label for="q">Search</label>
                        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name or place">
                    </div>
                    <div>
                        <label for="generation">Generation</label>
                        <select id="generation" name="generation">
                            <option value="">All generations</option>
                            <?php foreach ($genCounts as $gen): ?>
                                <option value="<?= e((int) $gen['generation']) ?>" <?= $filters['generation'] === (string) $gen['generation'] ? 'selected' : '' ?>>
                                    Generation <?= e((int) $gen['generation']) ?> (<?= e((int) $gen['total']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status">Record status</label>
                        <select id="status" name="status">
                            <option value="">Any status</option>
                            <?php foreach (['confirmed', 'proposed', 'unknown', 'disputed'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (!empty($branches)): ?>
                    <div class="form-row">
                        <label for="branch">Branch</label>
                        <select id="branch" name="branch">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e($branch['branch']) ?>" <?= $filters['branch'] === $branch['branch'] ? 'selected' : '' ?>>
                                    <?= e($branch['branch']) ?> (<?= e((int) $branch['total']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Apply filters</button>
                    <?php if ($activeCount > 0): ?>
                        <a class="text-small" href="<?= e(url($baseUrl)) ?>">Clear filters (<?= e($activeCount) ?>)</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (empty($ancestors)): ?>
            <div class="empty-state">
                <h3>No ancestor records<?php if ($activeCount > 0): ?> match the current filters<?php endif; ?></h3>
                <p>
                    <?php if ($canWrite): ?>
                        Add the first person this family remembers, or clear the filters above.
                    <?php else: ?>
                        Nothing has been recorded for this family yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Generation</th>
                                <th scope="col">Years</th>
                                <th scope="col">Branch</th>
                                <th scope="col">Record status</th>
                                <th scope="col"><span class="visually-hidden">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ancestors as $person): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url($baseUrl . '/' . (int) $person['id'])) ?>"><strong><?= e(\App\Models\Ancestor::displayName($person)) ?></strong></a>
                                        <?php if (!empty($person['is_living'])): ?><span class="badge badge--neutral">Living</span><?php endif; ?>
                                    </td>
                                    <td><?= e((int) $person['generation']) ?></td>
                                    <td class="text-small">
                                        <?php $birth = \App\Models\Ancestor::birthYear($person); $death = \App\Models\Ancestor::deathYear($person); ?>
                                        <?php if ($birth || $death): ?>
                                            <?= e($birth ? $birth : '?') ?> - <?= e($death ? $death : (!empty($person['is_living']) ? '' : '?')) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small"><?= e($person['branch'] ?: '-') ?></td>
                                    <td><?= status_badge($person['record_status']) ?></td>
                                    <td class="text-small"><a href="<?= e(url($baseUrl . '/' . (int) $person['id'])) ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-small text-muted">
                Showing <?= e((($page - 1) * $perPage) + 1) ?>-<?= e(min($page * $perPage, $total)) ?> of <?= e($total) ?> records.
                <?php if ($lastPage > 1): ?>
                    Page <?= e($page) ?> of <?= e($lastPage) ?>.
                    <?php if ($page > 1): ?>
                        <a href="<?= e(query_url($baseUrl, array_merge($queryBase, ['page' => $page - 1]))) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $lastPage): ?>
                        <a href="<?= e(query_url($baseUrl, array_merge($queryBase, ['page' => $page + 1]))) ?>">Next</a>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

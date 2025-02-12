<?php
    $useRowLink = $this->hasRecordAction();

    $classes = [
        'list-scrollable',
    ];

    if ($useRowLink) $classes[] = 'list-rowlink';
    if ($showCheckboxes) $classes[] = 'list-checkboxes';
?>
<div
    class="control-list <?= implode(' ', $classes) ?>"
    data-control="liststructurewidget"
    data-reorder-handler="<?= $this->getEventHandler('onReorder') ?>"
    data-toggle-handler="<?= $this->getEventHandler('onToggleTreeNode') ?>"
    data-use-reorder="<?= $showReorder ?>"
    data-use-tree="<?= $showTree ?>"
    data-drag-row="<?= $dragRow ?>"
    data-include-sort-orders="<?= $includeSortOrders ?>"
    data-indent-size="<?= $indentSize ?>"
    data-stripe-load-indicator
    <?php if ($maxDepth !== null): ?>data-max-depth="<?= $maxDepth ?>"<?php endif ?>
>
    <div class="list-content">
        <table class="table data" <?= $useRowLink ? 'data-control="rowlink"' : '' ?>>
            <thead>
                <?= $this->makePartial('list_head_row') ?>
            </thead>
            <tbody>
                <?php if (count($records)): ?>
                    <?= $this->makePartial('list_body_rows') ?>
                <?php else: ?>
                    <tr class="no-data no-sort">
                        <td colspan="<?= $columnTotal ?>" class="nolink">
                            <p class="no-data"><?= $noRecordsMessage ?></p>
                        </td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
        <?php if ($showPagination): ?>
            <div class="list-footer">
                <?php if ($showPageNumbers): ?>
                    <?= $this->makePartial('list_pagination') ?>
                <?php else: ?>
                    <?= $this->makePartial('list_pagination_simple') ?>
                <?php endif ?>
            </div>
        <?php endif ?>

        <?= $this->makePartial('list_datalocker') ?>
    </div>
</div>

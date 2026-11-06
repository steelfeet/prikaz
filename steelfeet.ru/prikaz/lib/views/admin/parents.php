<?php
declare(strict_types=1);
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Родители</h2>
            <p class="panel__subtitle">Привязка родителя к студенту. Родитель видит только своего ребёнка.</p>
        </div>
    </div>

    <form method="post" action="index.php?action=admin_parent_link"
          class="pzk-inline-form" autocomplete="off"
          style="margin-bottom: var(--space-sm);">
        <?= csrf_field() ?>
        <label>Родитель:
            <select name="parent_id" class="pzk-field__input">
                <?php foreach ($adminParents as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['full_name']) ?> (#<?= (int) $p['id'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Студент:
            <select name="student_id" class="pzk-field__input">
                <?php foreach ($adminStudents as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e($s['full_name']) ?> · <?= e($s['group_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="pzk-btn pzk-btn--primary">Привязать</button>
    </form>

    <div class="pzk-table-wrap">
        <table class="pzk-table">
            <thead>
                <tr>
                    <th>Родитель</th>
                    <th>Студент</th>
                    <th style="width:130px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminParentLinks as $l): ?>
                    <?php
                    $pName = $sName = '';
                    foreach ($adminParents as $p) {
                        if ((int) $p['id'] === (int) $l['parent_id']) { $pName = $p['full_name']; break; }
                    }
                    foreach ($adminStudents as $s) {
                        if ((int) $s['id'] === (int) $l['student_id']) { $sName = $s['full_name']; break; }
                    }
                    ?>
                    <tr>
                        <td><?= e($pName) ?></td>
                        <td><?= e($sName) ?></td>
                        <td class="pzk-table__actions">
                            <form method="post" action="index.php?action=admin_parent_unlink">
                                <?= csrf_field() ?>
                                <input type="hidden" name="parent_id" value="<?= (int) $l['parent_id'] ?>">
                                <input type="hidden" name="student_id" value="<?= (int) $l['student_id'] ?>">
                                <button type="submit" class="pzk-btn pzk-btn--sm">Отвязать</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($adminParentLinks === []): ?>
                    <tr><td colspan="3" class="pzk-table__empty">Пока ни одна связка не создана.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
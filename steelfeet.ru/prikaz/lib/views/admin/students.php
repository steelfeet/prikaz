<?php
declare(strict_types=1);
?>
<section class="panel">
    <div class="panel__head">
        <div>
            <h2 class="panel__title">Студенты</h2>
            <p class="panel__subtitle">Список студентов и их псевдонимов. Псевдоним используется в репозиториях — расшифровка только здесь.</p>
        </div>
    </div>
    <div class="pzk-table-wrap">
        <table class="pzk-table">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>ФИО</th>
                    <th style="width:90px;">Группа</th>
                    <th style="width:150px;">Псевдоним</th>
                    <th style="width:90px;">User ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminStudents as $s): ?>
                    <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td><?= e($s['full_name']) ?></td>
                        <td><?= e($s['group_name']) ?></td>
                        <td>
                            <?php if (!empty($s['alias'])): ?>
                                <code><?= e($s['alias']) ?></code>
                            <?php else: ?>
                                <span style="color:var(--color-tertiary);">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $s['user_id'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
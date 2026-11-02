<?php
declare(strict_types=1);

function phase_requires_confirmation(int $num): bool
{
    return $num > 0 && $num % 2 === 0;
}

function project_current_phase(array $proj): int
{
    if (empty($proj['history'])) return 0;
    $last = end($proj['history']);
    return (int) $last['num'];
}

function build_project_workflow(
    array $workflowSteps,
    array $sideLabels,
    array $stepStates,
    array $currentStates,
    int $phaseNum,
    int $projectId
): array {
    $currentState = $currentStates[$projectId % count($currentStates)];
    $workflow = [];
    foreach ($workflowSteps as $step) {
        if ($step['num'] < $phaseNum) $state = 'completed';
        elseif ($step['num'] === $phaseNum) $state = $currentState;
        else $state = 'pending';
        $workflow[] = [
            'num'        => $step['num'],
            'name'       => $step['name'],
            'side'       => $step['side'],
            'sideLabel'  => $sideLabels[$step['side']],
            'state'      => $state,
            'stateLabel' => $stepStates[$state]['label'],
            'color'      => $stepStates[$state]['color'],
            'fill'       => $stepStates[$state]['fill'],
            'isCurrent'  => $step['num'] === $phaseNum,
        ];
    }
    return $workflow;
}
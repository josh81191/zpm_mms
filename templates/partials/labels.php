<?php
$partyLabels = ['zpm' => 'ZPM', 'mnf' => 'MNF', 'congress' => 'Congress', 'bjp' => 'BJP', 'other' => 'Other'];
$classificationLabels = ['green' => 'Green', 'yellow' => 'Yellow', 'red' => 'Red'];
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

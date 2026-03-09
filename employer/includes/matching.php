<?php
function buildJobText(array $job): string {
    $parts = [
        $job['requirements'] ?? '',
        $job['experience_level'] ?? '',
        $job['location'] ?? '',
        $job['job_type'] ?? '',
        $job['salary_range'] ?? '',
        $job['education_requirement'] ?? ''
    ];

    return normalizeText(implode(' ', array_filter($parts)));
}

function buildCandidateText(array $candidate): string {
    $parts = [
        $candidate['skills'] ?? '',
        $candidate['experience_level'] ?? '',
        $candidate['location'] ?? '',
        $candidate['preferred_job_type'] ?? '',
        $candidate['preferred_salary_range'] ?? '',
        $candidate['highest_education'] ?? ''
    ];

    return normalizeText(implode(' ', array_filter($parts)));
}

function computeMatchScore(array $job, array $candidate): int {
    $jobSkills = $job['requirements'] ?? '';
    $candidateSkills = $candidate['skills'] ?? '';
    $jobExperience = normalizeExperienceLevel($job['experience_level'] ?? '');
    $candidateExperience = normalizeExperienceLevel($candidate['experience_level'] ?? '');

    if (normalizeText($jobSkills) === '' || normalizeText($candidateSkills) === '') {
        return 0;
    }

    if ($jobExperience === '' || $candidateExperience === '') {
        return 0;
    }

    $skillsSimilarity = computeExactOrTokenSimilarity($jobSkills, $candidateSkills);
    $experienceSimilarity = computeExactOrTokenSimilarity($jobExperience, $candidateExperience);

    $weighted = ($skillsSimilarity * 0.75) + ($experienceSimilarity * 0.25);
    return (int) round($weighted * 100);
}

function computeStrictSkillExperienceMatchScore(array $job, array $candidate): int {
    $jobSkills = $job['requirements'] ?? '';
    $candidateSkills = $candidate['skills'] ?? '';
    $jobExperience = normalizeExperienceLevel($job['experience_level'] ?? '');
    $candidateExperience = normalizeExperienceLevel($candidate['experience_level'] ?? '');

    // Return 0 if skills or experience are missing
    if (normalizeText($jobSkills) === '' || normalizeText($candidateSkills) === '') {
        return 0;
    }

    if ($jobExperience === '' || $candidateExperience === '') {
        return 0;
    }

    // Check if skills match exactly (same tokens, order doesn't matter)
    $skillsMatch = computeExactTokenSetMatch($jobSkills, $candidateSkills);
    
    // Check if experience matches exactly
    $experienceMatch = ($jobExperience === $candidateExperience);
    
    // Return 100% only if BOTH skills and experience match exactly
    return ($skillsMatch && $experienceMatch) ? 100 : 0;
}

function computeExactTextMatch(string $left, string $right): bool {
    $left = normalizeText($left);
    $right = normalizeText($right);

    if ($left === '' || $right === '') {
        return false;
    }

    return $left === $right;
}

function normalizeExperienceLevel(string $level): string {
    $normalized = normalizeText($level);

    if ($normalized === '') {
        return '';
    }

    $map = [
        'no experience' => '0-1',
        '0 1 year' => '0-1',
        '0 1 years' => '0-1',
        '0 to 1 year' => '0-1',
        '0 to 1 years' => '0-1',
        '1 2 year' => '1-2',
        '1 2 years' => '1-2',
        '1 to 2 year' => '1-2',
        '1 to 2 years' => '1-2',
        '2 5 year' => '2-5',
        '2 5 years' => '2-5',
        '2 to 5 year' => '2-5',
        '2 to 5 years' => '2-5',
        '5 10 year' => '5-10',
        '5 10 years' => '5-10',
        '5 to 10 year' => '5-10',
        '5 to 10 years' => '5-10',
        '10 year' => '10+',
        '10 years' => '10+',
        '10 years above' => '10+',
        '10 years and above' => '10+',
        '10 years or more' => '10+'
    ];

    return $map[$normalized] ?? $normalized;
}

function computeExperienceMatch(string $jobLevel, string $candidateLevel): bool {
    $jobNormalized = normalizeExperienceLevel($jobLevel);
    $candidateNormalized = normalizeExperienceLevel($candidateLevel);

    if ($jobNormalized === '' || $candidateNormalized === '') {
        return false;
    }

    return $jobNormalized === $candidateNormalized;
}

function computeExactTokenSetMatch(string $left, string $right): bool {
    $leftTokens = array_unique(tokenize(normalizeText($left)));
    $rightTokens = array_unique(tokenize(normalizeText($right)));

    if (empty($leftTokens) || empty($rightTokens)) {
        return false;
    }

    sort($leftTokens);
    sort($rightTokens);

    return $leftTokens === $rightTokens;
}

function normalizeText(string $text): string {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));

    return $text;
}

function tokenize(string $text): array {
    if ($text === '') {
        return [];
    }

    $tokens = explode(' ', $text);
    $filtered = [];
    foreach ($tokens as $token) {
        if (strlen($token) >= 2) {
            $filtered[] = $token;
        }
    }

    return $filtered;
}

function computeTokenSimilarity(string $left, string $right): float {
    $leftTokens = array_unique(tokenize(normalizeText($left)));
    $rightTokens = array_unique(tokenize(normalizeText($right)));

    if (empty($leftTokens) || empty($rightTokens)) {
        return 0.0;
    }

    $intersection = array_intersect($leftTokens, $rightTokens);
    return (2 * count($intersection)) / (count($leftTokens) + count($rightTokens));
}

function computeExactOrTokenSimilarity(string $left, string $right): float {
    $left = normalizeText($left);
    $right = normalizeText($right);

    if ($left === '' || $right === '') {
        return 0.0;
    }

    if ($left === $right) {
        return 1.0;
    }

    return computeTokenSimilarity($left, $right);
}

function computeSkillMatch(string $jobSkills, string $candidateSkills): bool {
    $jobTokens = array_unique(tokenize(normalizeText($jobSkills)));
    $candidateTokens = array_unique(tokenize(normalizeText($candidateSkills)));

    if (empty($jobTokens) || empty($candidateTokens)) {
        return false;
    }

    $intersection = array_intersect($jobTokens, $candidateTokens);
    return !empty($intersection);
}

function computeBehaviorBoost(int $appliedCount, int $savedCount): int {
    $appliedBoost = min(12, max(0, $appliedCount) * 4);
    $savedBoost = min(6, max(0, $savedCount) * 2);

    // TODO: Replace heuristic with ML model when available.
    return $appliedBoost + $savedBoost;
}
?>

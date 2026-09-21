<?php
/** Purpose: Apply DecisionPacks v1 typed answers and ordered gates in PHP.
 * Derived from gbesse/decisionpacks (MIT), commit 3c90b6e667c16653b9a6ae00b376df9bddbd8461.
 */
namespace JevRules;
final class DecisionPacks {
    public static function check($condition, string $message): void {
        if (!$condition) { throw new \InvalidArgumentException($message); }
    }
    public static function number($value): bool { return (is_int($value) || is_float($value)) && is_finite((float) $value); }
    public static function probability($value): void { self::check(self::number($value) && $value >= 0 && $value <= 1, 'Invalid probability'); }
    public static function path(string $field): array {
        $parts = explode('.', $field);
        foreach ($parts as $part) { self::check(preg_match('/^[a-zA-Z0-9_-]+$/', $part) && !in_array($part, ['__proto__', 'prototype', 'constructor'], true), 'Unsafe field path'); }
        return $parts;
    }
    public static function get(array $source, string $field) {
        $value = $source;
        foreach (self::path($field) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) { return null; }
            $value = $value[$key];
        }
        return $value;
    }
    public static function typed($value, string $kind): bool {
        return match ($kind) { 'string' => is_string($value), 'boolean' => is_bool($value), 'number' => self::number($value), default => false };
    }
    public static function validate(array $pack): void {
        self::check(($pack['schemaVersion'] ?? null) === 1, 'Invalid pack schema');
        self::check(is_string($pack['name'] ?? null) && preg_match('/^[a-z0-9][a-z0-9\/-]*$/', $pack['name']), 'Invalid pack name');
        self::check(is_string($pack['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $pack['version']), 'Invalid pack version');
        self::check(is_string($pack['model'] ?? null) && $pack['model'] !== '' && !preg_match('/latest|preview/', $pack['model']), 'Pin a model version');
        self::check(is_string($pack['description'] ?? null) && $pack['description'] !== '', 'Description required');
        self::check(is_array($pack['inputs'] ?? null) && count($pack['inputs']) > 0 && is_array($pack['questions'] ?? null) && count($pack['questions']) > 0 && count($pack['questions']) <= 128, 'Inputs and questions required');
        foreach ($pack['inputs'] as $field => $type) { self::path((string) $field); self::check(in_array($type, ['string', 'number', 'boolean'], true), 'Invalid input type'); }
        foreach ($pack['questions'] as $id => $q) {
            self::check(count(self::path((string) $id)) === 1 && is_array($q) && in_array($q['type'] ?? '', ['choice', 'noul', 'score'], true), 'Invalid question');
            self::check(is_string($q['instructions'] ?? null) && trim($q['instructions']) !== '', 'Question instructions required');
            if ($q['type'] === 'choice') {
                self::check(is_array($q['criteria'] ?? null) && count($q['criteria']) >= 2 && count($q['criteria']) <= 255, 'Invalid choices');
                foreach ($q['criteria'] as $key => $description) { self::check(count(self::path((string) $key)) === 1 && ($description === null || is_string($description)), 'Invalid choice'); }
            } elseif ($q['type'] === 'score') {
                self::check(is_array($q['criteria'] ?? null) && array_is_list($q['criteria']) && count($q['criteria']) >= 2 && count($q['criteria']) <= 10, 'Invalid score rubric');
                foreach ($q['criteria'] as $description) { self::check(is_string($description), 'Invalid rubric text'); }
            } elseif (isset($q['criteria'])) {
                self::check(is_array($q['criteria']), 'Invalid noul criteria');
                foreach ($q['criteria'] as $key => $description) { self::check(in_array($key, ['true', 'false'], true) && is_string($description), 'Invalid noul criterion'); }
            }
        }
        self::check(is_array($pack['rules'] ?? null) && is_string($pack['fallback'] ?? null) && $pack['fallback'] !== '', 'Rules and fallback required');
        $ids = [];
        foreach ($pack['rules'] as $rule) {
            self::check(is_string($rule['id'] ?? null) && $rule['id'] !== '' && !in_array($rule['id'], $ids, true), 'Invalid rule id'); $ids[] = $rule['id'];
            self::check(is_string($rule['outcome'] ?? null) && $rule['outcome'] !== '' && is_array($rule['all'] ?? null) && count($rule['all']) > 0, 'Invalid rule');
            foreach ($rule['all'] as $p) {
                $parts = self::path($p['field'] ?? ''); $value = $p['value'] ?? null;
                self::check(count($parts) > 1 && in_array($p['op'] ?? '', ['eq','neq','gt','gte','lt','lte'], true), 'Invalid predicate');
                self::check(is_string($value) || is_bool($value) || self::number($value), 'Predicate requires a scalar');
                self::check(in_array($p['op'], ['eq','neq'], true) || self::number($value), 'Ordered predicate requires a number');
                if ($parts[0] === 'state') {
                    $field = implode('.', array_slice($parts, 1)); self::check(isset($pack['inputs'][$field]) && self::typed($value, $pack['inputs'][$field]), 'Undeclared or mistyped input predicate');
                } else {
                    self::check($parts[0] === 'answers' && count($parts) >= 3 && isset($pack['questions'][$parts[1]]), 'Unknown answer predicate');
                    $q = $pack['questions'][$parts[1]]; $field = implode('.', array_slice($parts, 2));
                    $keys = $q['type'] === 'choice' ? array_keys($q['criteria']) : range(0, count($q['criteria'] ?? []) - 1);
                    $allowed = $q['type'] === 'noul' ? ['noul'] : array_merge(['confidence', $q['type'] === 'choice' ? 'choice' : 'score'], array_map(fn($k) => 'probabilities.' . $k, $keys));
                    self::check(in_array($field, $allowed, true), 'Invalid answer field');
                    if ($field === 'choice') { self::check(is_string($value) && array_key_exists($value, $q['criteria']), 'Unknown choice'); }
                    elseif ($field === 'score') { self::check(self::number($value), 'Expected score number'); }
                    else { self::probability($value); }
                }
            }
        }
    }
    public static function state(array $pack, array $state): void {
        foreach ($pack['inputs'] as $field => $kind) { self::check(self::typed(self::get($state, $field), $kind), 'Missing or mistyped input: ' . $field); }
        self::check(strlen(wp_json_encode($state, JSON_THROW_ON_ERROR)) <= 100000, 'Decision state exceeds size limit');
    }
    public static function answers(array $questions, array $answers): void {
        self::check(count($questions) === count($answers), 'Answer set mismatch');
        foreach ($questions as $id => $q) {
            $a = $answers[$id] ?? null; self::check(is_array($a) && ($a['type'] ?? null) === $q['type'], 'Answer type mismatch');
            if ($q['type'] === 'noul') { self::probability($a['noul'] ?? null); continue; }
            self::probability($a['confidence'] ?? null);
            $keys = $q['type'] === 'choice' ? array_keys($q['criteria']) : range(0, count($q['criteria']) - 1);
            $probs = $a['probabilities'] ?? null; self::check(is_array($probs) && count($probs) === count($keys), 'Probability set mismatch');
            foreach ($keys as $key) { self::check(array_key_exists($key, $probs), 'Missing probability'); self::probability($probs[$key]); }
            self::check(abs(array_sum($probs) - 1) <= .001, 'Probabilities must sum to one');
            if ($q['type'] === 'choice') { self::check(array_key_exists($a['choice'] ?? '', $probs) && $probs[$a['choice']] >= max($probs)-.001, 'Invalid selected choice'); }
            else { $expected = 0; foreach ($probs as $key => $value) { $expected += ((int) $key) * $value; } self::check(self::number($a['score'] ?? null) && $a['score'] >= 0 && $a['score'] <= count($keys)-1 && abs($a['score']-$expected) <= .01, 'Invalid expected score'); }
        }
    }
    public static function decide(array $pack, array $state, array $answers): array {
        self::validate($pack); self::state($pack, $state); self::answers($pack['questions'], $answers);
        foreach ($pack['rules'] as $rule) {
            $matches = true;
            foreach ($rule['all'] as $p) {
                $a = self::get(['state'=>$state, 'answers'=>$answers], $p['field']); $b = $p['value'];
                if ($a === null || (gettype($a) !== gettype($b) && !(self::number($a) && self::number($b)))) { $matches = false; break; }
                $same = self::number($a) && self::number($b) ? $a == $b : $a === $b;
                $ok = match ($p['op']) { 'eq'=>$same, 'neq'=>!$same, 'gt'=>$a>$b, 'gte'=>$a>=$b, 'lt'=>$a<$b, 'lte'=>$a<=$b };
                if (!$ok) { $matches = false; break; }
            }
            if ($matches) { return ['outcome'=>$rule['outcome'], 'ruleId'=>$rule['id']]; }
        }
        return ['outcome'=>$pack['fallback'], 'ruleId'=>null];
    }
}

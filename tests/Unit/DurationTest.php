<?php

use App\Support\ClockTime;
use App\Support\Duration;

it('formats durations', function (int $seconds, string $expected) {
    expect(Duration::format($seconds))->toBe($expected);
})->with([
    [0, '0:00:00'],
    [59, '0:00:59'],
    [3725, '1:02:05'],
    [90000, '25:00:00'],
]);

it('parses duration input', function (string $input, ?int $expected) {
    expect(Duration::parse($input))->toBe($expected);
})->with([
    ['1:30', 5400],
    ['1:30:15', 5415],
    ['1.5', 5400],
    ['1,25', 4500],
    ['2', 7200],
    ['130', 5400],
    ['0145', 6300],
    ['1h 30m', 5400],
    ['45m', 2700],
    ['1:75', null],
    ['abc', null],
    ['', null],
]);

it('parses clock times', function (string $input, ?array $expected) {
    expect(ClockTime::parse($input))->toBe($expected);
})->with([
    ['9', [9, 0]],
    ['930', [9, 30]],
    ['09:30', [9, 30]],
    ['21.45', [21, 45]],
    ['9:30pm', [21, 30]],
    ['12am', [0, 0]],
    ['12:15 PM', [12, 15]],
    ['25:00', null],
    ['13pm', null],
    ['x', null],
]);

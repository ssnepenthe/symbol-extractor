<?php

namespace UnionTypes;

function alfa(string|int $bravo): void {}

function charlie(): string|int
{
    return '';
}

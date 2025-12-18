<?php

namespace {
    if (! function_exists('alfa')) {
        function alfa() {}
    }

    // We should only get one of these in our output...
    if (true) {
        class Bravo {}
    } else {
        class Bravo extends DateTimeImmutable {}
    }
}

namespace Charlie {
    if (! function_exists(__NAMESPACE__ . '\\delta')) {
        function delta() {}
    }
}

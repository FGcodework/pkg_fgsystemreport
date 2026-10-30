# Tests

Dependency-free tests for the pure-logic parts (security score, disk scan,
history comparison, growth detection, accepted risk). No Joomla and no
Composer needed:

    php tests/run.php

`bootstrap.php` stubs only what those classes touch. Each `*Test.php` returns
`name => callable`; a test fails by throwing (see the `assert*()` helpers in
`run.php`). The tests are not part of the installable package.

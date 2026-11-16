<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use RuntimeException;

/** Something staff asked for that can't be done; the message says why, for them. */
class RentalException extends RuntimeException {}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A hotel rule stopped the action (room already taken, bill not paid, booking closed...).
 *
 * Services throw it with a message written for front-desk staff. bootstrap/app.php turns it into
 * a redirect back to the form with that message shown as a red alert, so controllers stay short.
 */
class BookingException extends RuntimeException {}

<?php

namespace App\Services\LuisFigueroa\Mensajeria;

use RuntimeException;

/** The broker could not be reached or refused the publication (a transport failure). */
class BrokerNoDisponibleException extends RuntimeException {}

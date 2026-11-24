<?php

namespace App\Services\Search;

use RuntimeException;

/**
 * The search cluster could not be reached or rejected the request.
 */
class SearchUnavailableException extends RuntimeException {}

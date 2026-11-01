<?php

/**
 * @package ValidationException
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies HttpException (libs/Exceptions/HttpException.php)
 * @description Thrown when input fails validation (HTTP 422). Request::validate() throws it.
 */

namespace JHz\Exceptions;

/**
 * Thrown when input fails validation (HTTP 422). Request::validate() throws it.
 * Catch it if you want to handle failures yourself (e.g. redirect back to a form):
 *
 *   try { $data = $req->validate([...]); }
 *   catch (ValidationException $e) { $e->errors(); }
 */
class ValidationException extends HttpException {
    private $errors;

    /**
     * @param array  $errors  ['field' => ['message', ...]]
     * @param string $message Summary shown to the client (default: a generic sentence).
     */
    public function __construct(array $errors, $message = '', $previous = null) {
        $this->errors = $errors;
        parent::__construct(422, $message !== '' ? $message : 'The given data was invalid.', [], $previous);
    }

    public function errors() {
        return $this->errors;
    }

    public function getTitle() {
        return 'Validation failed';
    }

    public function getData() {
        return ['errors' => $this->errors];
    }
}

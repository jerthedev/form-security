<?php

declare(strict_types=1);

namespace JTD\FormSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JTD\FormSecurity\Contracts\FormSecurityContract;

/**
 * FormSecurity middleware for protecting routes from spam and malicious submissions.
 *
 * This middleware automatically analyzes form submissions and blocks
 * or flags suspicious content based on configuration settings.
 */
class FormSecurityMiddleware
{
    /**
     * Create a new middleware instance.
     *
     * @param  FormSecurityContract  $formSecurity  FormSecurity service
     */
    public function __construct(
        protected FormSecurityContract $formSecurity
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$options): mixed
    {
        // Only process POST requests with form data
        if (! $request->isMethod('POST') || ! $request->hasAny(['_token'])) {
            return $next($request);
        }

        // Skip if package is disabled
        if (! $this->formSecurity->getConfig('enabled', true)) {
            return $next($request);
        }

        // Analyze the form submission
        $data = $request->all();
        $analysis = $this->formSecurity->analyzeSubmission($data);

        // Handle based on analysis results
        if (! $analysis['valid']) {
            return $this->handleSpamDetection($request, $analysis, $options);
        }

        return $next($request);
    }

    /**
     * Handle spam detection results.
     *
     * @param  array<string, mixed>  $analysis
     * @param  array<string>  $options
     */
    protected function handleSpamDetection(Request $request, array $analysis, array $options): Response
    {
        $action = $this->formSecurity->getConfig('spam_action', 'block');

        return match ($action) {
            'block' => $this->blockSubmission($request, $analysis),
            'flag' => $this->flagSubmission($request, $analysis),
            'log' => $this->logSubmission($request, $analysis),
            default => $this->blockSubmission($request, $analysis),
        };
    }

    /**
     * Block the submission and return an error response.
     *
     * @param  array<string, mixed>  $analysis
     */
    protected function blockSubmission(Request $request, array $analysis): Response
    {
        return response()->json([
            'error' => 'Submission blocked due to security concerns.',
            'code' => 'SPAM_DETECTED',
        ], 422);
    }

    /**
     * Flag the submission but allow it to continue.
     *
     * @param  array<string, mixed>  $analysis
     */
    protected function flagSubmission(Request $request, array $analysis): Response
    {
        // Add flag to request for later processing
        $request->merge(['_spam_flagged' => true, '_spam_analysis' => $analysis]);

        return response()->json([
            'warning' => 'Submission flagged for review.',
            'code' => 'FLAGGED_FOR_REVIEW',
        ], 200);
    }

    /**
     * Log the submission and allow it to continue.
     *
     * @param  array<string, mixed>  $analysis
     */
    protected function logSubmission(Request $request, array $analysis): Response
    {
        // Log the suspicious submission
        logger('Suspicious form submission detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'analysis' => $analysis,
        ]);

        return response()->json([
            'message' => 'Submission processed.',
            'code' => 'LOGGED',
        ], 200);
    }
}

<?php

namespace Quizgecko\AbTesting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class GenerateAbidMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get abid from cookie or header (using helper)
        $abid = abid();

        // If the user is authenticated
        if ($user = Auth::user()) {
            // Check if the user model has an abid property
            if (property_exists($user, 'abid')) {
                if ($user->abid) {
                    // Use the user's stored abid
                    $abid = $user->abid;
                    set_abid($abid); // Ensure cookie is set/updated
                } elseif (!$abid) {
                    // If user has no abid and no cookie/header abid, generate a new one
                    $abid = $this->generateId();
                    $user->abid = $abid;
                    $user->save();
                    set_abid($abid);
                } else {
                    // If there's a cookie/header abid but user has none, save it to user
                    $user->abid = $abid;
                    $user->save();
                }
            } elseif (!$abid) {
                // User model doesn't have abid, and no cookie/header abid found, generate temporary one
                $abid = $this->generateId();
                set_abid($abid);
            }
            // If user model doesn't have abid property but a cookie/header abid exists,
            // we just use the cookie/header abid. No need to generate or save.

        } elseif (!$abid) {
            // For non-authenticated users without an abid cookie/header, generate a new one
            $abid = $this->generateId();
            set_abid($abid);
        }

        // Add abid to request attributes so it's available via $request->attributes->get('abid')
        // and potentially picked up by the abid() helper later in the same request cycle.
        if ($abid) {
            $request->attributes->set('abid', $abid);
        }

        return $next($request);
    }

    /**
     * Generate a unique identifier.
     *
     * @return string
     */
    private function generateId(): string
    {
        // Combine uniqid with more random characters for better uniqueness
        return uniqid('ab_', true) . '-' . Str::random(16);
    }
}
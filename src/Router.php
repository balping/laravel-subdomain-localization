<?php

namespace Hoyvoy\Localization;

use Illuminate\Http\Request;

class Router
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * An array that contains all routes that should be translated.
     *
     * @var array
     */
    protected $translatedRoutes = [];

    /**
     * An array that contains information about the current request.
     *
     * @var array
     */
    protected $parsed_url;

    /**
     * Adds the detected locale to the current unlocalized URL.
     *
     * @return string
     */
    public function getRedirectURL()
    {
        $parsed_url = parse_url(app()['request']->fullUrl());

        // Add locale to the host
        $locale = $this->aliasLocale(app()->getLocale());
        $parsed_url['host'] = $locale.'.'.$this->getDomain();

        return $this->unparseUrl($parsed_url);
    }

    /**
     * Translate the current route for the given locale.
     *
     * @param $locale
     *
     * @return bool|string
     */
    public function current($locale)
    {
        return $this->url($this->getCurrentRouteName(), $this->getCurrentRouteAttributes(), $locale);
    }

    /**
     * Translate the current route for the given locale.
     *
     * @param bool $excludeCurrentLocale
     *
     * @return array
     */
    public function getCurrentVersions($excludeCurrentLocale = true)
    {
        $versions = [];

        foreach (app()['localization.localize']->getAvailableLocales() as $locale) {
            if ($excludeCurrentLocale && $locale == app()->getLocale()) {
                continue;
            }

            if ($url = $this->current($locale)) {
                $versions[$locale] = $url;
            }
        }

        return $versions;
    }

    /**
     * Return translated URL from route.
     *
     * @param string       $routeName
     * @param string|false $routeAttributes
     * @param string|false $locale
     *
     * @return string|bool
     */
    public function url($routeName, $routeAttributes = null, $locale = null)
    {
        // If no locale is given, we use the current locale
        if (!$locale) {
            $locale = app()->getLocale();
        }

        if (!$this->parsed_url) {
            $this->parseCurrentUrl();
        }

        // Retrieve the current URL components
        $parsed_url = $this->parsed_url;

        // Add locale to the host
        $parsed_url['host'] = $this->aliasLocale($locale).'.'.$this->getDomain();

        // Resolve the route path for the given route name
        if (!$parsed_url['path'] = $this->findRoutePathByName($routeName, $locale)) {
            return false;
        }

        // If attributes are given, substitute them in the path
        if ($routeAttributes) {
            $parsed_url['path'] = $this->substituteAttributesInRoute($routeAttributes, $parsed_url['path']);
        }

        return $this->unparseUrl($parsed_url);
    }

    /**
     * Resolve a translated route path for the given route name.
     *
     * @param $routeName
     *
     * @return string
     */
    public function resolve($routeName)
    {
        $routePath = $this->findRoutePathByName($routeName);

        if (!isset($this->translatedRoutes[$routeName])) {
            $this->translatedRoutes[$routeName] = $routePath;
        }

        return $routePath;
    }

    /**
     * Get the current route name.
     *
     * @return bool|string
     */
    protected function getCurrentRouteName()
    {
        if (app()['router']->currentRouteName()) {
            return app()['router']->currentRouteName();
        }

        if (app()['router']->current()) {
            return $this->findRouteNameByPath(app()['router']->current()->uri());
        }

        return false;
    }

    /**
     * Get the current route name.
     *
     * @return bool|string
     */
    protected function getCurrentRouteAttributes()
    {
        if (app()['router']->current()) {
            return app()['router']->current()->parametersWithoutNulls();
        }

        return false;
    }

    /**
     * Find the route name matching the given route path.
     *
     * @param string $routePath
     *
     * @return bool|string
     */
    protected function findRouteNameByPath($routePath)
    {
        foreach ($this->translatedRoutes as $name => $path) {
            if ($routePath == $path) {
                return $name;
            }
        }

        return false;
    }

    /**
     * Find the route path matching the given route name.
     *
     * @param string      $routeName
     * @param string|null $locale
     *
     * @return string
     */
    protected function findRoutePathByName($routeName, $locale = null)
    {
        if (app()['translator']->has($routeName, $locale)) {
            return app()['translator']->trans($routeName, [], $locale);
        }

        return false;
    }

    /**
     * Get url using array data from parse_url.
     *
     * @param array|false $parsed_url Array of data from parse_url function
     *
     * @return string Returns URL as string.
     */
    protected function unparseUrl($parsed_url)
    {
        if (empty($parsed_url)) {
            return '';
        }

        $url = '';
        $url .= isset($parsed_url['scheme']) ? $parsed_url['scheme'].'://' : '';
        $url .= isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $url .= isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':'.$parsed_url['pass'] : '';
        $url .= $user.(($user || $pass) ? "$pass@" : '');

        if (!empty($url)) {
            $url .= isset($parsed_url['path']) ? '/'.ltrim($parsed_url['path'], '/') : '';
        } elseif (empty($url)) {
            $url .= isset($parsed_url['path']) ? $parsed_url['path'] : '';
        }

        $url .= isset($parsed_url['query']) ? '?'.$parsed_url['query'] : '';
        $url .= isset($parsed_url['fragment']) ? '#'.$parsed_url['fragment'] : '';

        return $url;
    }

    /**
     * Change route attributes for the ones in the $attributes array.
     *
     * @param $attributes array Array of attributes
     * @param string $route string route to substitute
     *
     * @return string route with attributes changed
     */
    protected function substituteAttributesInRoute($attributes, $route)
    {
        foreach ($attributes as $key => $value) {
            $route = str_replace('{'.$key.'}', $value, $route);
            $route = str_replace('{'.$key.'?}', $value, $route);
        }

        // delete empty optional arguments that are not in the $attributes array
        $route = preg_replace('/\/{[^)]+\?}/', '', $route);

        return $route;
    }

    /**
     * Stores the parsed url array after a few modifications.
     *
     * @return void
     */
    protected function parseCurrentUrl()
    {
        $parsed_url = parse_url(app()['request']->fullUrl());

        // Don't store path, query and fragment
        unset($parsed_url['path']);
        unset($parsed_url['query']);
        unset($parsed_url['fragment']);

        $parsed_url['host'] = $this->getDomain();

        $this->parsed_url = $parsed_url;
    }

    /**
     * Get domain from package config.
     *
     * @return string
     */
    protected function getDomain()
    {
        return app()['config']->get('localization.domain');
    }

    /**
     * If locale is aliased, return alias.
     *
     * @param string $locale Locale to be aliased eg. 'de'
     *
     * @return string aliased locale eg. 'german'
     */
    protected function aliasLocale($locale)
    {
        $aliases = app()['config']->get('localization.aliases', []);
        $alias = array_search($locale, $aliases);

        if ($alias) {
            $locale = $alias;
        }

        return $locale;
    }
}

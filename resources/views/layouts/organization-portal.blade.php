@extends((auth()->check() && auth()->user()->isSuperAdmin()) ? 'layouts.admin' : 'layouts.organization')

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p class="mb-3">{{ __('You are logged in!') }}</p>

                    <a class="btn btn-primary" href="{{ route('users.manage') }}">Gestión de usuarios</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<div>
    <div class="login card p-4" style="margin-top:30px">
		<img src="{{asset('Logo.png')}}" class="rounded mx-auto d-block" alt="...">
			<p>Password Guidelines:</p>
			<ol>
				<li>You will have to change your password compulsorily every 90 calendar days</li>
				<li>The password should be minimum 9 characters</li>
				<li>The password should be combination of characters set:
					<ul>
						<li>Alphabets(A-Z)(a-z)</li>
						<li>Numbers(0-9)</li>
						<li>Special Characters</li>
						<li>One Capital Characters</li>
					</ul>
				</li>
			</ol>
			<div class="md-form ">
				<i class="fas fa-lock prefix"></i>
                <input id="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" wire:model.defer="current_password" required>
                <label for="current_password">Current Password</label>
				<div class="error invalid-feedback">
					@error('current_password') {{ $message }} @enderror
				</div>
			</div>
            
			<div class="md-form ">
				<i class="fas fa-lock prefix"></i>
                <input type="password" id='password' class="form-control @error('current_password') is-invalid @enderror" wire:model.defer="password" required>
                <label for="password">New Password</label>
				<div class="error invalid-feedback">
					@error('password') {{ $message }} @enderror
				</div>
			</div>
            
            <div class="md-form ">
				<i class="fas fa-lock prefix"></i>
                <input id="password_confirmation" type="password" class="form-control @error('current_password') is-invalid @enderror" wire:model.defer="password_confirmation" required>
                <label for="password_confirmation">Confirm New Password</label>
				<div class="error invalid-feedback">
					@error('password_confirmation') {{ $message }} @enderror
				</div>
			</div>
			<div class="md-form">
				<button type="button" class="btn btn-primary btn-lg btn-block" wire:click="submit"   wire:offline.attr="disabled" wire:loading.attr="disabled">
					<span class="" wire:loading.class="spinner-grow spinner-grow-sm"></span>
				  Reset Password
				</button>
			</div>
	</div>
</div>
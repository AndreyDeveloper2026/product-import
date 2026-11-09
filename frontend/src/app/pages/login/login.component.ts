import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, MatCardModule, MatFormFieldModule, MatInputModule, MatButtonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page narrow">
      <mat-card>
        <mat-card-header><mat-card-title>Вход</mat-card-title></mat-card-header>
        <mat-card-content>
          <form [formGroup]="form" (ngSubmit)="submit()" class="form">
            <mat-form-field>
              <mat-label>Логин</mat-label>
              <input matInput formControlName="username" autocomplete="username" data-testid="username" />
            </mat-form-field>
            <mat-form-field>
              <mat-label>Пароль</mat-label>
              <input
                matInput
                type="password"
                formControlName="password"
                autocomplete="current-password"
                data-testid="password"
              />
            </mat-form-field>
            @if (error()) {
              <p class="err" data-testid="login-error">{{ error() }}</p>
            }
            <button mat-flat-button color="primary" type="submit" [disabled]="form.invalid || busy()">
              Войти
            </button>
          </form>
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .narrow { max-width: 380px; }
      .form { display: flex; flex-direction: column; gap: 8px; padding-top: 16px; }
      .err { color: #b00020; margin: 0; }
    `,
  ],
})
export class LoginComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    username: ['', Validators.required],
    password: ['', Validators.required],
  });

  protected submit(): void {
    if (this.form.invalid) {
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    const { username, password } = this.form.getRawValue();

    this.auth.login(username, password).subscribe({
      next: () => {
        const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl') ?? '/products';
        void this.router.navigateByUrl(returnUrl);
      },
      error: () => {
        this.busy.set(false);
        this.error.set('Неверный логин или пароль');
      },
    });
  }
}

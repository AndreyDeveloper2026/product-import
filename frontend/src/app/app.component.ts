import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatToolbarModule } from '@angular/material/toolbar';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <mat-toolbar color="primary">
      <span>Каталог товаров</span>
      @if (auth.isLoggedIn()) {
        <a mat-button routerLink="/products" routerLinkActive="active">Товары</a>
        <a mat-button routerLink="/import" routerLinkActive="active">Импорт</a>
        <span class="spacer"></span>
        <button mat-button (click)="logout()">Выйти</button>
      }
    </mat-toolbar>
    <router-outlet />
  `,
  styles: [
    `
      a.active {
        background: rgba(255, 255, 255, 0.18);
      }
      mat-toolbar a {
        margin-left: 16px;
      }
    `,
  ],
})
export class AppComponent {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}

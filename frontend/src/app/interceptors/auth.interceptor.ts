import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

/** Добавляет Bearer-токен и глобально обрабатывает 401 (-> /login) и 5xx (snackbar). */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  const token = auth.token();
  const request =
    token !== null && req.url.startsWith('/api')
      ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
      : req;

  return next(request).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && !req.url.endsWith('/auth/login')) {
        auth.logout();
        void router.navigate(['/login']);
      } else if (err.status >= 500) {
        snackBar.open('Ошибка сервера. Попробуйте позже.', 'OK', { duration: 5000 });
      }
      return throwError(() => err);
    }),
  );
};

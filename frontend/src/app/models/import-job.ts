export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ImportReportEntry {
  row: number;
  level: 'error' | 'warning';
  message: string;
}

export interface ImportJob {
  id: number;
  status: ImportStatus;
  originalName: string;
  totalRows: number;
  processedRows: number;
  succeededRows: number;
  failedRows: number;
  progress: number;
  report: ImportReportEntry[];
  errorMessage: string | null;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export const isActive = (job: ImportJob): boolean =>
  job.status === 'pending' || job.status === 'processing';

export interface ProductAttribute {
  key: string;
  value: string;
}

export interface ProductImage {
  url: string;
  path: string;
  publicUrl: string;
}

/** Элемент списка (лёгкая версия товара). */
export interface ProductSummary {
  id: number;
  externalCode: string;
  name: string;
  price: number;
  discount: number;
  thumbnailUrl: string | null;
}

/** Карточка товара. */
export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  price: number;
  discount: number;
  attributes: ProductAttribute[];
  images: ProductImage[];
}

export interface Paginated<T> {
  items: T[];
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ProductQuery {
  page: number;
  limit: number;
  name: string;
  priceMin: number | null;
  priceMax: number | null;
}

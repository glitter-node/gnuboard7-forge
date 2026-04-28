import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const templateRoot = path.resolve(__dirname, '..', '..');
const commerceModule = ['sirsoft', 'ecommerce'].join('-');
const routeBaseToken = ['shop', 'Base'].join('');
const cartHeaderToken = ['X', 'Cart', 'Key'].join('-');
const cartInitToken = ['init', 'Cart', 'Key'].join('');
const currencyStorageToken = ['g7', 'preferred', 'currency'].join('_');

function readText(relativePath: string): string {
  return readFileSync(path.join(templateRoot, relativePath), 'utf-8');
}

function readJson(relativePath: string) {
  return JSON.parse(readText(relativePath));
}

describe('sirsoft-comm community-only template', () => {
  it('removes ecommerce dependencies and routes from active template JSON', () => {
    const manifest = readJson('template.json');
    const routes = readJson('routes.json');
    const routePaths = routes.routes.map((route: { path: string }) => route.path);

    expect(manifest.dependencies.modules[commerceModule]).toBeUndefined();
    expect(routePaths).not.toContain('/mypage/orders');
    expect(routePaths).not.toContain('/mypage/orders/:order_number');
    expect(routePaths).not.toContain('/mypage/wishlist');
    expect(routePaths).not.toContain('/mypage/addresses');
    expect(routePaths).not.toContain('/mypage/inquiries');
    expect(routePaths.some((routePath: string) => routePath.includes(commerceModule))).toBe(false);
  });

  it('removes ecommerce wiring from the base, home, and search layouts', () => {
    const userBase = readText('layouts/_user_base.json');
    const home = readText('layouts/home.json');
    const search = readText('layouts/search/index.json');
    const searchTabs = readText('layouts/partials/search/_search_tabs.json');
    const searchFilters = readText('layouts/partials/search/_search_filters.json');
    const searchResults = readText('layouts/partials/search/_search_results.json');

    expect(userBase).not.toContain(commerceModule);
    expect(userBase).not.toContain(routeBaseToken);
    expect(userBase).not.toContain(cartHeaderToken);
    expect(userBase).not.toContain(cartInitToken);
    expect(userBase).not.toContain(currencyStorageToken);
    expect(userBase).not.toContain('/mypage/orders');
    expect(userBase).not.toContain('/mypage/wishlist');

    expect(home).not.toContain('partials/home/_shop_promo.json');

    expect(search).not.toContain(commerceModule);
    expect(searchTabs).not.toContain('search.tabs.products');
    expect(searchFilters).not.toContain("searchActiveTab === 'products'");
    expect(searchResults).not.toContain('partials/search/products/_section.json');
    expect(searchResults).not.toContain('search.empty.products');
  });

  it('removes desktop ecommerce navigation from the header component source', () => {
    const header = readText('src/components/composite/Header.tsx');
    const mobileNav = readText('src/components/composite/MobileNav.tsx');

    expect(header).not.toContain("navigate('/mypage/orders')");
    expect(header).not.toContain("navigate('/mypage/wishlist')");
    expect(header).not.toContain(`navigate(\`\${${routeBaseToken}}/cart\`)`);
    expect(header).not.toContain("t('nav.shop')");
    expect(header).not.toContain('cartCount > 0');

    expect(mobileNav).not.toContain("navigate('/shop')");
    expect(mobileNav).not.toContain("navigate('/cart')");
    expect(mobileNav).not.toContain("t('nav.shop')");
    expect(mobileNav).not.toContain("t('nav.cart')");
    expect(mobileNav).not.toContain('cartCount');
  });

  it('removes mypage translation keys for orders, wishlist, and addresses', () => {
    const mypageKo = readText('lang/partial/ko/mypage.json');
    const mypageEn = readText('lang/partial/en/mypage.json');
    const userKo = readText('lang/partial/ko/user.json');
    const userEn = readText('lang/partial/en/user.json');

    expect(mypageKo).not.toContain('"orders_title"');
    expect(mypageKo).not.toContain('"wishlist_title"');
    expect(mypageKo).not.toContain('"addresses_title"');
    expect(mypageKo).not.toContain('"warning_orders"');

    expect(mypageEn).not.toContain('"orders_title"');
    expect(mypageEn).not.toContain('"wishlist_title"');
    expect(mypageEn).not.toContain('"addresses_title"');
    expect(mypageEn).not.toContain('"warning_orders"');

    expect(userKo).not.toContain('"orders_title"');
    expect(userKo).not.toContain('"wishlist_title"');
    expect(userKo).not.toContain('"addresses_title"');
    expect(userEn).not.toContain('"orders_title"');
    expect(userEn).not.toContain('"wishlist_title"');
    expect(userEn).not.toContain('"addresses_title"');
  });
});

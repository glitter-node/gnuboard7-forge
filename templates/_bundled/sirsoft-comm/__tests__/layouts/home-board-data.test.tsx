import React from 'react';
import { describe, expect, it, afterEach } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  createLayoutTest,
  createMockComponentRegistryWithBasics,
  screen,
} from '@core/template-engine/__tests__/utils/layoutTestUtils';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const templateRoot = path.resolve(__dirname, '..', '..');

function readJson<T = any>(relativePath: string): T {
  return JSON.parse(readFileSync(path.join(templateRoot, relativePath), 'utf-8')) as T;
}

function resolvePartials<T = any>(value: T): T {
  if (Array.isArray(value)) {
    return value.map((item) => resolvePartials(item)) as T;
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const record = value as Record<string, any>;
  if (typeof record.partial === 'string') {
    return resolvePartials(readJson(path.join('layouts', record.partial)));
  }

  return Object.fromEntries(
    Object.entries(record).map(([key, child]) => [key, resolvePartials(child)])
  ) as T;
}

function createRegistry() {
  const registry = createMockComponentRegistryWithBasics();

  registry.register('basic', 'Icon', ({ name, className }) => (
    <span className={className} data-icon={name} />
  ));
  registry.register('layout', 'Container', ({ children, className }) => (
    <div className={className}>{children}</div>
  ));

  return registry;
}

function createHomeLayoutTest() {
  const homeLayout = resolvePartials(readJson('layouts/home.json'));
  const testUtils = createLayoutTest(homeLayout, {
    componentRegistry: createRegistry(),
    templateId: 'sirsoft-comm',
    locale: 'ko',
    translations: {
      home: readJson('lang/partial/ko/home.json'),
      board: readJson('lang/partial/ko/board.json'),
      common: readJson('lang/partial/ko/common.json'),
    },
  });

  return testUtils;
}

describe('sirsoft-comm home board data bindings', () => {
  let testUtils: ReturnType<typeof createHomeLayoutTest> | null = null;

  afterEach(() => {
    testUtils?.cleanup();
    testUtils = null;
  });

  it('renders public board API data on the homepage', async () => {
    testUtils = createHomeLayoutTest();

    testUtils.mockApi('stats', {
      response: {
        data: {
          users: 11,
          boards: 3,
          posts: 1,
          comments: 0,
        },
      },
    });
    testUtils.mockApi('recent_posts', {
      response: {
        data: [
          {
            id: 6,
            board_slug: 'notice',
            board_name: '공지사항',
            title: 'Welcome to the notice board',
            created_at: '2026-05-03 05:51',
            created_at_formatted: '7분 전',
            comment_count: 0,
            is_secret: false,
            is_new: true,
          },
        ],
      },
    });
    testUtils.mockApi('popular_boards', {
      response: {
        data: [
          { id: 30, name: '공지사항', slug: 'notice', posts_count: 1 },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0 },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0 },
        ],
      },
    });
    testUtils.mockApi('home_boards', {
      response: {
        data: [
          { id: 99, name: '기타게시판', slug: 'archive', posts_count: 12, recent_posts: [] },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0, recent_posts: [] },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0, recent_posts: [] },
          {
            id: 30,
            name: '공지사항',
            slug: 'notice',
            posts_count: 1,
            recent_posts: [
              {
                id: 6,
                title: 'Welcome to the notice board',
                created_at: '2026-05-03 05:51',
                created_at_formatted: '7분 전',
                comment_count: 0,
                is_notice: true,
                is_secret: false,
                is_new: true,
              },
            ],
          },
        ],
      },
    });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getAllByText('공지사항').length).toBeGreaterThan(0);
    expect(screen.getAllByText('자유게시판').length).toBeGreaterThan(0);
    expect(screen.getAllByText('질문게시판').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Welcome to the notice board').length).toBeGreaterThan(0);
    expect(screen.getByText('11')).toBeInTheDocument();
    expect(screen.getAllByText('3').length).toBeGreaterThan(0);
    expect(screen.queryByText('기타게시판')).not.toBeInTheDocument();
  });

  it('preserves homepage empty states when board arrays are empty', async () => {
    testUtils = createHomeLayoutTest();

    testUtils.mockApi('stats', {
      response: { data: { users: 0, boards: 0, posts: 0, comments: 0 } },
    });
    testUtils.mockApi('recent_posts', { response: { data: [] } });
    testUtils.mockApi('popular_boards', { response: { data: [] } });
    testUtils.mockApi('home_boards', { response: { data: [] } });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getByText('첫 대화를 시작할 준비가 되었습니다')).toBeInTheDocument();
    expect(screen.getByText('활동에 따라 인기 게시판이 정렬됩니다')).toBeInTheDocument();
    expect(screen.getByText('설정 후 추천 게시판이 표시됩니다')).toBeInTheDocument();
  });
});

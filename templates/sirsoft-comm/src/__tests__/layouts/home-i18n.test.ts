import { describe, expect, it, beforeEach } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { TranslationEngine, type TranslationContext } from '@/core/template-engine/TranslationEngine';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const templateRoot = path.resolve(__dirname, '..', '..', '..');

function readText(relativePath: string): string {
  return readFileSync(path.join(templateRoot, relativePath), 'utf-8');
}

function readJson<T>(relativePath: string): T {
  return JSON.parse(readText(relativePath)) as T;
}

function loadDictionary(locale: 'ko' | 'en') {
  return {
    home: readJson(`lang/partial/${locale}/home.json`),
    board: readJson(`lang/partial/${locale}/board.json`),
  };
}

describe('home layout i18n enforcement', () => {
  const koContext: TranslationContext = {
    templateId: 'sirsoft-comm',
    locale: 'ko',
  };

  const enContext: TranslationContext = {
    templateId: 'sirsoft-comm',
    locale: 'en',
  };

  beforeEach(() => {
    TranslationEngine.resetInstance();
    const engine = TranslationEngine.getInstance();
    (engine as any).translations.set('sirsoft-comm:ko', loadDictionary('ko'));
    (engine as any).translations.set('sirsoft-comm:en', loadDictionary('en'));
  });

  it('uses translation keys instead of hardcoded homepage text in touched partials', () => {
    const welcomeCard = readText('layouts/partials/home/_welcome_card.json');
    const boardSummary = readText('layouts/partials/home/_board_summary.json');
    const communityGuide = readText('layouts/partials/home/_community_guide.json');
    const recentPosts = readText('layouts/partials/home/_recent_posts.json');

    expect(welcomeCard).toContain('$t:home.hero_title');
    expect(welcomeCard).not.toContain('Sir Soft Community');
    expect(welcomeCard).not.toContain('{{_global.settings?.general?.site_name}}$t:home.hero_title_suffix');

    expect(boardSummary).toContain('$t:board.new_badge');
    expect(boardSummary).toContain('$t:home.comment_count_badge|count={{post.comment_count}}');
    expect(boardSummary).not.toContain('"text": "N"');
    expect(boardSummary).not.toContain('"text": "[{{post.comment_count}}]"');

    expect(recentPosts).toContain('$t:board.new_badge');
    expect(recentPosts).toContain('$t:home.comment_count_badge|count={{post.comment_count}}');
    expect(recentPosts).not.toContain('"text": "N"');
    expect(recentPosts).not.toContain('"text": "[{{post.comment_count}}]"');

    expect(communityGuide).toContain('$t:home.guide_bullet');
    expect(communityGuide).not.toContain('"text": "•"');
  });

  it('uses Button variant and size props for the primary hero CTA', () => {
    const welcomeCard = readJson<any>('layouts/partials/home/_welcome_card.json');
    const cta = welcomeCard.children[3].children[3].children[0];

    expect(cta.name).toBe('Button');
    expect(cta.props.variant).toBe('primary');
    expect(cta.props.size).toBe('md');
    expect(cta.props.className).toBe('gap-2 cursor-pointer');
    expect(cta.props.className).not.toContain('btn-primary-bg');
    expect(cta.props.className).not.toMatch(/\bbg-amber-/);
    expect(cta.props.className).not.toMatch(/\btext-amber-/);
    expect(cta.props.className).not.toMatch(/\bborder-amber-/);
  });

  it('renders homepage text correctly in Korean mode', () => {
    const engine = TranslationEngine.getInstance();

    expect(engine.translate('home.hero_title', koContext)).toBe('Sir Soft Community');
    expect(engine.translate('home.recent_posts_empty_title', koContext)).toBe('첫 게시글을 기다리고 있습니다');
    expect(engine.translate('home.popular_boards_empty_title', koContext)).toBe('활동에 따라 목록이 채워집니다');
    expect(engine.translate('home.comment_count_badge', koContext, '|count=12')).toBe('[12]');
    expect(engine.translate('home.guide_bullet', koContext)).toBe('•');
    expect(engine.translate('board.new_badge', koContext)).toBe('NEW');
  });

  it('renders homepage text correctly in English mode', () => {
    const engine = TranslationEngine.getInstance();

    expect(engine.translate('home.hero_title', enContext)).toBe('Sir Soft Community');
    expect(engine.translate('home.recent_posts_empty_title', enContext)).toBe('Ready for the first post');
    expect(engine.translate('home.popular_boards_empty_title', enContext)).toBe('Activity will shape this list');
    expect(engine.translate('home.comment_count_badge', enContext, '|count=12')).toBe('[12]');
    expect(engine.translate('home.guide_bullet', enContext)).toBe('•');
    expect(engine.translate('board.new_badge', enContext)).toBe('NEW');
  });

  it('changes homepage hero text when the locale changes', () => {
    const engine = TranslationEngine.getInstance();

    const korean = engine.translate('home.hero_description', koContext);
    const english = engine.translate('home.hero_description', enContext);

    expect(korean).toBe('게시판, 게시글, 회원 대화를 위한 배포용 커뮤니티 홈입니다.');
    expect(english).toBe('A ready-to-use community home for boards, posts, and member discussions.');
    expect(korean).not.toBe(english);
  });
});

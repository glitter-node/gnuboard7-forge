<?php

namespace Modules\Sirsoft\Board\Traits;

use App\Helpers\TimezoneHelper;
use Carbon\Carbon;

/**
 * 게시판 날짜 포맷 유틸리티 Trait
 *
 * PostResource, CommentResource에서 공유하는 날짜 포맷 로직.
 * 전역 설정(display.date_display_format)에 따라 표준형 또는 유동형으로 포맷합니다.
 */
trait FormatsBoardDate
{
    /**
     * 게시글/댓글 작성일을 표시용 문자열로 포맷합니다.
     *
     * @param  mixed   $dateTime  날짜/시간 (Carbon, string, null)
     * @param  string  $format    포맷 방식 ('standard' | 'relative')
     * @return string  포맷된 날짜 문자열
     */
    protected function formatCreatedAtFormat(mixed $dateTime, string $format = 'standard'): string
    {
        if (! $dateTime) {
            return '';
        }

        $locale = $this->boardDateLocale();
        $isKorean = str_starts_with($locale, 'ko');
        $carbon = $dateTime instanceof Carbon ? $dateTime : Carbon::parse($dateTime);
        $userCarbon = TimezoneHelper::toUserCarbon($carbon);
        $now = TimezoneHelper::toUserCarbon(Carbon::now());

        $diffInMinutes = (int) $now->diffInMinutes($userCarbon, absolute: true);
        $diffInHours = (int) $now->diffInHours($userCarbon, absolute: true);

        // 1시간 미만: N분 전 (공통)
        if ($diffInMinutes < 60) {
            if ($diffInMinutes < 1) {
                return $isKorean ? '방금 전' : 'just now';
            }

            // 10분 이상은 10분 단위로 내림 (예: 21분 → 20분 전)
            if ($diffInMinutes >= 10) {
                $rounded = (int) floor($diffInMinutes / 10) * 10;

                return $isKorean ? $rounded.'분 전' : $this->englishRelativeTime($rounded, 'minute');
            }

            return $isKorean ? $diffInMinutes.'분 전' : $this->englishRelativeTime($diffInMinutes, 'minute');
        }

        // 1~23시간: N시간 전 (공통)
        if ($diffInHours < 24) {
            return $isKorean ? $diffInHours.'시간 전' : $this->englishRelativeTime($diffInHours, 'hour');
        }

        if ($format === 'relative') {
            // 유동형: N일 전 → N개월 전 → N년 전
            $diffInDays = (int) $now->diffInDays($userCarbon, absolute: true);
            $diffInMonths = (int) $now->diffInMonths($userCarbon, absolute: true);
            $diffInYears = (int) $now->diffInYears($userCarbon, absolute: true);

            if ($diffInYears >= 1) {
                return $isKorean ? $diffInYears.'년 전' : $this->englishRelativeTime($diffInYears, 'year');
            }

            if ($diffInMonths >= 1) {
                return $isKorean ? $diffInMonths.'개월 전' : $this->englishRelativeTime($diffInMonths, 'month');
            }

            return $isKorean ? $diffInDays.'일 전' : $this->englishRelativeTime($diffInDays, 'day');
        }

        // 표준형: MM-DD (올해) → YY-MM-DD (지난해 이전)
        if ($userCarbon->year === $now->year) {
            return $userCarbon->format('m-d');
        }

        return $userCarbon->format('y-m-d');
    }

    /**
     * 게시글/댓글 작성일을 요일 포함 전체 날짜+시간 문자열로 포맷합니다.
     *
     * 예시: "2026-03-18 화요일 14:30"
     *
     * @param  mixed  $dateTime  날짜/시간 (Carbon, string, null)
     * @return string  요일 포함 날짜 문자열
     */
    protected function formatCreatedAt(mixed $dateTime): string
    {
        if (! $dateTime) {
            return '';
        }

        $carbon = $dateTime instanceof Carbon ? $dateTime : Carbon::parse($dateTime);
        $userCarbon = TimezoneHelper::toUserCarbon($carbon);

        return $userCarbon->locale($this->boardDateLocale())->translatedFormat('Y-m-d l H:i');
    }

    /**
     * 현재 요청/앱 locale을 Carbon에서 사용할 수 있는 날짜 locale로 정규화합니다.
     */
    protected function boardDateLocale(): string
    {
        $user = auth()->user();
        if ($user && ! empty($user->language)) {
            return str_replace('_', '-', (string) $user->language);
        }

        $acceptLanguage = request()?->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = trim(explode(',', $acceptLanguage)[0]);
            $locale = str_contains($locale, '-') ? explode('-', $locale)[0] : $locale;

            if (in_array($locale, ['ko', 'en'], true)) {
                return $locale;
            }
        }

        $locale = (string) app()->getLocale();

        return str_replace('_', '-', $locale ?: 'ko');
    }

    /**
     * 영어 상대 시간 문자열을 생성합니다.
     */
    protected function englishRelativeTime(int $value, string $unit): string
    {
        return $value.' '.$unit.($value === 1 ? '' : 's').' ago';
    }
}

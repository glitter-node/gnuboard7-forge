import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Button } from '../Button';

describe('Button 컴포넌트', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    delete (window as any).G7Core;
  });

  it('기본 variant와 size 클래스를 적용한다', () => {
    render(<Button>확인</Button>);

    const button = screen.getByRole('button', { name: '확인' });
    expect(button).toHaveClass('btn', 'btn-primary', 'px-4', 'py-2', 'text-sm');
  });

  it('문서화된 variant 클래스를 적용한다', () => {
    const variants = [
      ['primary', 'btn-primary'],
      ['secondary', 'btn-secondary'],
      ['danger', 'btn-danger'],
      ['success', 'btn-success'],
      ['ghost', 'btn-ghost'],
      ['outline', 'btn-outline'],
    ] as const;

    variants.forEach(([variant, expectedClass]) => {
      const { unmount } = render(<Button variant={variant}>{variant}</Button>);
      expect(screen.getByRole('button', { name: variant })).toHaveClass(expectedClass);
      unmount();
    });
  });

  it('문서화된 size 클래스를 적용한다', () => {
    render(
      <>
        <Button size="sm">small</Button>
        <Button size="md">medium</Button>
        <Button size="lg">large</Button>
      </>
    );

    expect(screen.getByRole('button', { name: 'small' })).toHaveClass('px-3', 'py-1.5', 'text-sm');
    expect(screen.getByRole('button', { name: 'medium' })).toHaveClass('px-4', 'py-2', 'text-sm');
    expect(screen.getByRole('button', { name: 'large' })).toHaveClass('px-5', 'py-3', 'text-sm');
  });

  it('G7Core mergeClasses로 외부 className을 병합한다', () => {
    const mergeClasses = vi.fn((baseClasses: string, overrideClasses?: string) => `${baseClasses} ${overrideClasses ?? ''}`.trim());
    (window as any).G7Core = {
      style: {
        mergeClasses,
      },
    };

    render(<Button className="gap-2 cursor-pointer">이동</Button>);

    expect(mergeClasses).toHaveBeenCalledWith('btn btn-primary px-4 py-2 text-sm', 'gap-2 cursor-pointer');
    expect(screen.getByRole('button', { name: '이동' })).toHaveClass('btn-primary', 'gap-2', 'cursor-pointer');
  });
});

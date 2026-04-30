import React from 'react';
import { Div } from '../basic/Div';

export interface ThreeColumnLayoutProps {
  
  leftWidth?: string;

  
  rightWidth?: string;

  
  leftSlot?: React.ReactNode;

  
  centerSlot?: React.ReactNode;

  
  rightSlot?: React.ReactNode;

  
  className?: string;

  
  style?: React.CSSProperties;
}


export const ThreeColumnLayout: React.FC<ThreeColumnLayoutProps> = ({
  leftWidth = '250px',
  rightWidth = '300px',
  leftSlot,
  centerSlot,
  rightSlot,
  className = '',
  style,
}) => {
  
  const containerClasses = `flex flex-row w-full h-full ${className}`.trim();

  // 왼쪽 영역 스타일
  const leftStyle: React.CSSProperties = {
    width: leftWidth,
    flexShrink: 0,
  };

  // 가운데 영역 스타일 (남은 공간 차지)
  const centerStyle: React.CSSProperties = {
    flex: 1,
    minWidth: 0, // flex 아이템 오버플로우 방지
  };

  // 오른쪽 영역 스타일
  const rightStyle: React.CSSProperties = {
    width: rightWidth,
    flexShrink: 0,
  };

  return (
    <Div className={containerClasses} style={style}>
      {/* 왼쪽 영역 */}
      <Div className="flex flex-col" style={leftStyle}>
        {leftSlot}
      </Div>

      {/* 가운데 영역 */}
      <Div className="flex flex-col" style={centerStyle}>
        {centerSlot}
      </Div>

      {/* 오른쪽 영역 */}
      <Div className="flex flex-col" style={rightStyle}>
        {rightSlot}
      </Div>
    </Div>
  );
};

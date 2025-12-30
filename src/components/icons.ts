import { html } from 'lit';

// =========================================================================
// Action Icons
// =========================================================================

export const editIcon = html`
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
    </svg>
`;

export const refreshIcon = html`
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
    </svg>
`;

export const checkIcon = html`
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
    </svg>
`;

export const closeIcon = html`
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
    </svg>
`;

// =========================================================================
// Toggle Icons
// =========================================================================

export const syncOnIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#28a745" stroke="#28a745" stroke-width="2"/>
        <circle cx="26" cy="10" r="6" fill="white"/>
        <path d="M24 10l1.5 1.5 3-3" stroke="#28a745" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
`;

export const syncOffIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#6c757d" stroke="#6c757d" stroke-width="2"/>
        <circle cx="10" cy="10" r="6" fill="white"/>
    </svg>
`;

export const lockOnIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#dc3545" stroke="#dc3545" stroke-width="2"/>
        <circle cx="26" cy="10" r="6" fill="white"/>
        <path d="M26 7v2h-1.5c-.28 0-.5.22-.5.5v3c0 .28.22.5.5.5h3c.28 0 .5-.22.5-.5v-3c0-.28-.22-.5-.5-.5H26V7c0-.55-.45-1-1-1s-1 .45-1 1" stroke="#dc3545" stroke-width="1" stroke-linecap="round"/>
    </svg>
`;

export const lockOffIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#6c757d" stroke="#6c757d" stroke-width="2"/>
        <circle cx="10" cy="10" r="6" fill="white"/>
    </svg>
`;

export const pathOnIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#0d6efd" stroke="#0d6efd" stroke-width="2"/>
        <circle cx="26" cy="10" r="6" fill="white"/>
        <path d="M24 10h4M26 8v4" stroke="#0d6efd" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
`;

export const pathOffIcon = html`
    <svg width="30" height="18" viewBox="0 0 36 20" fill="none">
        <rect x="1" y="1" width="34" height="18" rx="9" fill="#6c757d" stroke="#6c757d" stroke-width="2"/>
        <circle cx="10" cy="10" r="6" fill="white"/>
    </svg>
`;

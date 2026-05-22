import React from 'react';
import { render } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { vi } from 'vitest';

export const renderWithRouter = (ui, options = {}) => {
    return render(ui, {
        wrapper: ({ children }) => <BrowserRouter>{children}</BrowserRouter>,
        ...options,
    });
};

export const mockJsonResponse = (status, body) => ({
    ok: status >= 200 && status < 300,
    status,
    statusText: status === 200 ? 'OK' : `Status ${status}`,
    headers: {
        get: (h) => (h.toLowerCase() === 'content-type' ? 'application/json' : null),
    },
    json: async () => body,
    blob: async () => new Blob([JSON.stringify(body)], { type: 'application/json' }),
});

export const setupFetchRouter = (routes) => {
    const mock = vi.fn(async (url, init = {}) => {
        const method = (init.method || 'GET').toUpperCase();
        // Buscar match
        for (const [pattern, handler] of Object.entries(routes)) {
            const [patMethod, patPath] = pattern.split(/\s+/);
            if (patMethod.toUpperCase() !== method) continue;
            if (url.includes(patPath)) {
                const body = init.body ? safeParseJson(init.body) : null;
                const result = handler(body, url, init);
                return result instanceof Promise ? result : result;
            }
        }
        console.warn(`[mockFetch] sin match para ${method} ${url}`);
        return mockJsonResponse(404, { success: false, message: 'Sin handler en mock' });
    });

    global.fetch = mock;
    return mock;
};

const safeParseJson = (body) => {
    if (typeof body !== 'string') return body;
    try {
        return JSON.parse(body);
    } catch {
        return body;
    }
};

export const cleanTestEnv = () => {
    if (typeof localStorage !== 'undefined') localStorage.clear();
    if (typeof sessionStorage !== 'undefined') sessionStorage.clear();
};

export const mockSwal = (overrides = {}) => {
    return {
        fire: vi.fn(async (config) => ({
            isConfirmed: true,
            isDenied: false,
            isDismissed: false,
            value: undefined,
            ...overrides,
        })),
        showLoading: vi.fn(),
        close: vi.fn(),
    };
};

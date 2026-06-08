import { LogLevel, type Configuration, PublicClientApplication } from '@azure/msal-browser';

// Vite envs
const clientId = import.meta.env?.VITE_AAD_CLIENT_ID as string | undefined;
const tenantId = (import.meta.env?.VITE_AAD_TENANT_ID as string | undefined) || 'common';

export const isEntraConfigured = Boolean(clientId && clientId.trim().length > 0);

export const msalConfig: Configuration = {
  auth: {
    clientId: clientId || 'MISSING_CLIENT_ID',
    authority: `https://login.microsoftonline.com/${tenantId}`,
    redirectUri: window.location.origin,
  },
  cache: {
    cacheLocation: 'localStorage',
    storeAuthStateInCookie: false,
  },
  system: {
    loggerOptions: {
      logLevel: LogLevel.Warning,
      loggerCallback: () => {
        // keep quiet in UI
      },
    },
  },
};

export const loginRequest = {
  scopes: ['openid', 'profile', 'email'],
};

export const msalInstance = new PublicClientApplication(msalConfig);

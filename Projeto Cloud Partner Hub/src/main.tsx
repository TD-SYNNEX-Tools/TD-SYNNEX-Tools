import React from 'react'
import ReactDOM from 'react-dom/client'
import App from '../cloud_partner_hub.tsx'
import './index.css'
import { MsalProvider } from '@azure/msal-react'
import { msalInstance } from './auth/msalConfig'

msalInstance
  .handleRedirectPromise()
  .then((response) => {
    if (response?.account) {
      msalInstance.setActiveAccount(response.account);
      return;
    }

    const accounts = msalInstance.getAllAccounts();
    if (accounts.length > 0) {
      msalInstance.setActiveAccount(accounts[0]);
    }
  })
  .catch(() => {
    // ignore redirect errors at bootstrap; surfaced on login action
  });

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <MsalProvider instance={msalInstance}>
      <App />
    </MsalProvider>
  </React.StrictMode>,
)

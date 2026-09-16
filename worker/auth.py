"""Bearer token authentication middleware."""

from fastapi import HTTPException, Security
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from . import config

security = HTTPBearer(auto_error=False)


async def verify_token(
    credentials: HTTPAuthorizationCredentials | None = Security(security),
) -> str:
    """Verify bearer token against configured secret."""
    if not config.WORKER_TOKEN:
        raise HTTPException(status_code=500, detail="Worker token not configured")

    if credentials is None:
        raise HTTPException(status_code=401, detail="Missing authorization header")

    if credentials.credentials != config.WORKER_TOKEN:
        raise HTTPException(status_code=403, detail="Invalid token")

    return credentials.credentials

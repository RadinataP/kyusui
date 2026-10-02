package com.kyusui.app.core

object Constants {
    const val BASE_URL_PROD = "https://api.kyusui.example.com/api/v1/"
    const val BASE_URL_DEV = "http://10.0.2.2:8000/api/v1/"
    const val AUTH_HEADER = "Authorization"
    const val ACCEPT_HEADER = "Accept"
    const val BEARER_PREFIX = "Bearer "
    const val CONTENT_TYPE_JSON = "application/json"
    const val ACCEPT_JSON = "application/json"
    const val DEFAULT_TIMEOUT_SECONDS = 30L
    const val MAX_RETRIES = 3
    const val DATASTORE_NAME = "kyusui_preferences"
    const val REDACTED = "***REDACTED***"
}

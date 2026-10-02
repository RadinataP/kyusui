package com.kyusui.app.data.api;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class AuthInterceptor_Factory implements Factory<AuthInterceptor> {
  private final Provider<AuthTokenStore> authTokenStoreProvider;

  public AuthInterceptor_Factory(Provider<AuthTokenStore> authTokenStoreProvider) {
    this.authTokenStoreProvider = authTokenStoreProvider;
  }

  @Override
  public AuthInterceptor get() {
    return newInstance(authTokenStoreProvider.get());
  }

  public static AuthInterceptor_Factory create(Provider<AuthTokenStore> authTokenStoreProvider) {
    return new AuthInterceptor_Factory(authTokenStoreProvider);
  }

  public static AuthInterceptor newInstance(AuthTokenStore authTokenStore) {
    return new AuthInterceptor(authTokenStore);
  }
}

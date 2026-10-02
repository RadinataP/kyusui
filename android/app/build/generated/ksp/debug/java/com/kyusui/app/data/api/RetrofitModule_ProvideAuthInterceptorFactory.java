package com.kyusui.app.data.api;

import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
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
public final class RetrofitModule_ProvideAuthInterceptorFactory implements Factory<AuthInterceptor> {
  private final Provider<AuthTokenStore> authTokenStoreProvider;

  public RetrofitModule_ProvideAuthInterceptorFactory(
      Provider<AuthTokenStore> authTokenStoreProvider) {
    this.authTokenStoreProvider = authTokenStoreProvider;
  }

  @Override
  public AuthInterceptor get() {
    return provideAuthInterceptor(authTokenStoreProvider.get());
  }

  public static RetrofitModule_ProvideAuthInterceptorFactory create(
      Provider<AuthTokenStore> authTokenStoreProvider) {
    return new RetrofitModule_ProvideAuthInterceptorFactory(authTokenStoreProvider);
  }

  public static AuthInterceptor provideAuthInterceptor(AuthTokenStore authTokenStore) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideAuthInterceptor(authTokenStore));
  }
}

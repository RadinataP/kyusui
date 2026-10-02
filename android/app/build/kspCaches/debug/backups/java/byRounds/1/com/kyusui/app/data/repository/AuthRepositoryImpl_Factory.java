package com.kyusui.app.data.repository;

import com.kyusui.app.data.api.AuthApi;
import com.kyusui.app.data.datastore.PreferencesDataSource;
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
public final class AuthRepositoryImpl_Factory implements Factory<AuthRepositoryImpl> {
  private final Provider<AuthApi> authApiProvider;

  private final Provider<PreferencesDataSource> preferencesRepositoryProvider;

  public AuthRepositoryImpl_Factory(Provider<AuthApi> authApiProvider,
      Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    this.authApiProvider = authApiProvider;
    this.preferencesRepositoryProvider = preferencesRepositoryProvider;
  }

  @Override
  public AuthRepositoryImpl get() {
    return newInstance(authApiProvider.get(), preferencesRepositoryProvider.get());
  }

  public static AuthRepositoryImpl_Factory create(Provider<AuthApi> authApiProvider,
      Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    return new AuthRepositoryImpl_Factory(authApiProvider, preferencesRepositoryProvider);
  }

  public static AuthRepositoryImpl newInstance(AuthApi authApi,
      PreferencesDataSource preferencesRepository) {
    return new AuthRepositoryImpl(authApi, preferencesRepository);
  }
}

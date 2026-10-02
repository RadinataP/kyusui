package com.kyusui.app.data.api;

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
public final class AuthTokenStore_Factory implements Factory<AuthTokenStore> {
  private final Provider<PreferencesDataSource> preferencesRepositoryProvider;

  public AuthTokenStore_Factory(Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    this.preferencesRepositoryProvider = preferencesRepositoryProvider;
  }

  @Override
  public AuthTokenStore get() {
    return newInstance(preferencesRepositoryProvider.get());
  }

  public static AuthTokenStore_Factory create(
      Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    return new AuthTokenStore_Factory(preferencesRepositoryProvider);
  }

  public static AuthTokenStore newInstance(PreferencesDataSource preferencesRepository) {
    return new AuthTokenStore(preferencesRepository);
  }
}
